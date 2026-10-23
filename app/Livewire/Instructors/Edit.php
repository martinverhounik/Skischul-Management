<?php

namespace App\Livewire\Instructors;

use App\Livewire\Concerns\RequiresAdmin;
use App\Livewire\Forms\InstructorForm;
use App\Models\Instructor;
use App\Models\Sport;
use Livewire\Component;

/** Add an instructor (US-20), edit or deactivate one (US-21). Owner only. */
class Edit extends Component
{
    use RequiresAdmin;

    public InstructorForm $form;

    public function mount(?Instructor $instructor = null): void
    {
        if ($instructor?->exists) {
            $this->form->setInstructor($instructor->load('sports'));
        }
    }

    /** Check the IBAN as soon as the field is left, to catch typing mistakes early. */
    public function updatedFormIban(): void
    {
        $this->form->validateOnly('iban');
    }

    public function save(): void
    {
        $isNew = ! $this->form->instructor?->exists;
        $instructor = $this->form->save();

        session()->flash('status', $isNew
            ? __(':name has been created.', ['name' => $instructor->fullName()])
            : __(':name has been saved.', ['name' => $instructor->fullName()]));

        $this->redirectRoute('instructors.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.instructors.edit', [
            'sports' => Sport::query()->orderBy('position')->get(),
            'languages' => InstructorForm::LANGUAGES,
        ])->title($this->form->instructor?->exists ? $this->form->instructor->fullName() : __('Add instructor'));
    }
}
