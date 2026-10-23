<?php

namespace App\Livewire\Forms;

use App\Models\Student;
use App\Support\CurrentSchool;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The student form (US-18, US-19). A Livewire Form object, so the same
 * fields and rules are reused on the student pages and, in step 5, inside
 * the booking screen ("add a new student via the student form").
 */
class StudentForm extends Form
{
    public ?Student $student = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $date_of_birth = '';

    public string $skill_level_id = '';

    public string $primary_phone = '';

    public string $primary_phone_owner = '';

    public string $secondary_phone = '';

    public string $secondary_phone_owner = '';

    public string $preferred_language = '';

    public string $accommodation = '';

    public string $medical_notes = '';

    public string $notes = '';

    public function setStudent(Student $student): void
    {
        $this->student = $student;
        $this->fill([
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'date_of_birth' => $student->date_of_birth->toDateString(),
            'skill_level_id' => (string) $student->skill_level_id,
            'primary_phone' => $student->primary_phone,
            'primary_phone_owner' => $student->primary_phone_owner,
            'secondary_phone' => $student->secondary_phone ?? '',
            'secondary_phone_owner' => $student->secondary_phone_owner ?? '',
            'preferred_language' => $student->preferred_language ?? '',
            'accommodation' => $student->accommodation ?? '',
            'medical_notes' => $student->medical_notes ?? '',
            'notes' => $student->notes ?? '',
        ]);
    }

    protected function rules(): array
    {
        $schoolId = app(CurrentSchool::class)->id();

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'skill_level_id' => ['required', Rule::exists('skill_levels', 'id')->where('school_id', $schoolId)],
            'primary_phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\/\s.-]{5,}$/'],
            'primary_phone_owner' => ['required', 'string', 'max:100'],
            'secondary_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\/\s.-]{5,}$/', 'required_with:secondary_phone_owner'],
            'secondary_phone_owner' => ['nullable', 'string', 'max:100', 'required_with:secondary_phone'],
            'preferred_language' => ['nullable', 'string', 'max:50'],
            'accommodation' => ['nullable', 'string', 'max:255'],
            'medical_notes' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'first_name' => __('First name'),
            'last_name' => __('Last name'),
            'date_of_birth' => __('Date of birth'),
            'skill_level_id' => __('Skill level'),
            'primary_phone' => __('Phone'),
            'primary_phone_owner' => __('Whose phone'),
            'secondary_phone' => __('Second phone'),
            'secondary_phone_owner' => __('Whose second phone'),
            'preferred_language' => __('Language'),
            'accommodation' => __('Accommodation'),
            'medical_notes' => __('Medical notes'),
            'notes' => __('Notes'),
        ];
    }

    public function save(): Student
    {
        $data = $this->validate();
        foreach ($data as $key => $value) {
            if ($value === '') {
                $data[$key] = null;
            }
        }

        $student = $this->student ?? new Student;
        $student->fill($data)->save();
        $this->student = $student;

        return $student;
    }

    /**
     * Other students with the same name and date of birth: probably the
     * same person entered twice. Only a hint, never blocking.
     */
    public function possibleDuplicates(): Collection
    {
        if ($this->first_name === '' || $this->last_name === '' || $this->date_of_birth === '') {
            return collect();
        }

        return Student::query()
            ->where('first_name', $this->first_name)
            ->where('last_name', $this->last_name)
            ->whereDate('date_of_birth', $this->date_of_birth)
            ->when($this->student?->exists, fn ($q) => $q->whereKeyNot($this->student->id))
            ->limit(3)
            ->get();
    }
}
