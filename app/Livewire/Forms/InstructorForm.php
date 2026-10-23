<?php

namespace App\Livewire\Forms;

use App\Models\Instructor;
use App\Rules\Iban;
use App\Support\CurrentSchool;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** The instructor form (US-20, US-21). Used by owners only. */
class InstructorForm extends Form
{
    public const LANGUAGES = ['DE', 'EN', 'NL', 'FR', 'IT', 'ES', 'CS', 'PL', 'HU', 'DA', 'SV'];

    public ?Instructor $instructor = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $date_of_birth = '';

    public string $email = '';

    public string $phone = '';

    public string $iban = '';

    public string $social_security_number = '';

    public string $bic = '';

    public string $hourly_rate = '';

    public string $certification_level = '';

    /** @var list<string> */
    public array $languages = [];

    /** @var list<string> sport ids */
    public array $sport_ids = [];

    public string $notes = '';

    public bool $is_active = true;

    public function setInstructor(Instructor $instructor): void
    {
        $this->instructor = $instructor;
        $this->fill([
            'first_name' => $instructor->first_name,
            'last_name' => $instructor->last_name,
            'date_of_birth' => $instructor->date_of_birth->toDateString(),
            'email' => $instructor->email,
            'phone' => $instructor->phone,
            'iban' => Iban::format($instructor->iban),
            'social_security_number' => $instructor->social_security_number,
            'bic' => $instructor->bic ?? '',
            'hourly_rate' => $instructor->hourly_rate_cents === null ? '' : Money::toInput($instructor->hourly_rate_cents),
            'certification_level' => $instructor->certification_level ?? '',
            'languages' => $instructor->languages ?? [],
            'sport_ids' => $instructor->sports->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'notes' => $instructor->notes ?? '',
            'is_active' => $instructor->is_active,
        ]);
    }

    protected function rules(): array
    {
        $schoolId = app(CurrentSchool::class)->id();

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'email' => ['required', 'email', 'max:255',
                Rule::unique('instructors', 'email')->where('school_id', $schoolId)->ignore($this->instructor)],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\/\s.-]{5,}$/'],
            'iban' => ['required', 'string', new Iban],
            'social_security_number' => ['required', 'string', 'max:30', 'regex:/^[0-9A-Za-z\s\/-]{4,30}$/'],
            'bic' => ['nullable', 'string', 'regex:/^[A-Za-z]{6}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/'],
            'hourly_rate' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value !== '' && Money::parse($value) === null) {
                    $fail(__('Please enter an amount such as 25,50.'));
                }
            }],
            'certification_level' => ['nullable', 'string', 'max:150'],
            'languages' => ['array'],
            'languages.*' => [Rule::in(self::LANGUAGES)],
            'sport_ids' => ['array'],
            'sport_ids.*' => [Rule::exists('sports', 'id')->where('school_id', $schoolId)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'first_name' => __('First name'),
            'last_name' => __('Last name'),
            'date_of_birth' => __('Date of birth'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'iban' => 'IBAN',
            'social_security_number' => __('Social security number'),
            'bic' => 'BIC / SWIFT',
            'hourly_rate' => __('Hourly rate'),
            'certification_level' => __('Certification'),
            'notes' => __('Notes'),
        ];
    }

    public function save(): Instructor
    {
        // Normalise first, so the uniqueness check compares like with like.
        $this->email = mb_strtolower(trim($this->email));
        $data = $this->validate();

        return DB::transaction(function () use ($data) {
            $instructor = $this->instructor ?? new Instructor;
            $instructor->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'date_of_birth' => $data['date_of_birth'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'],
                'iban' => Iban::normalize($data['iban']),
                'social_security_number' => trim($data['social_security_number']),
                'bic' => $data['bic'] ? strtoupper($data['bic']) : null,
                'hourly_rate_cents' => $data['hourly_rate'] === '' || $data['hourly_rate'] === null ? null : Money::parse($data['hourly_rate']),
                'certification_level' => $data['certification_level'] ?: null,
                'languages' => array_values(array_intersect(self::LANGUAGES, $data['languages'])),
                'notes' => $data['notes'] ?: null,
                'is_active' => $data['is_active'],
            ])->save();

            $instructor->sports()->sync(array_map('intval', $data['sport_ids']));
            $this->instructor = $instructor;

            return $instructor;
        });
    }
}
