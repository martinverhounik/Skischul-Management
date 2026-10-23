<?php

namespace App\Livewire\Students;

use App\Livewire\Forms\StudentForm;
use App\Models\SkillLevel;
use App\Models\Student;
use Livewire\Component;

/** Add a student (US-18), edit or archive one (US-19). Staff and owners. */
class Edit extends Component
{
    public StudentForm $form;

    public function mount(?Student $student = null): void
    {
        if ($student?->exists) {
            $this->form->setStudent($student);
        }
    }

    public function save(): void
    {
        $isNew = ! $this->form->student?->exists;
        $student = $this->form->save();

        session()->flash('status', $isNew
            ? __(':name has been created.', ['name' => $student->fullName()])
            : __(':name has been saved.', ['name' => $student->fullName()]));

        $this->redirectRoute('students.index', navigate: true);
    }

    public function archive(): void
    {
        $student = $this->form->student;
        abort_unless($student?->exists, 404);

        $student->forceFill(['archived_at' => $student->archived_at ? null : now()])->save();

        session()->flash('status', $student->archived_at
            ? __(':name has been archived.', ['name' => $student->fullName()])
            : __(':name is active again.', ['name' => $student->fullName()]));

        $this->redirectRoute('students.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.students.edit', [
            'levels' => SkillLevel::query()->orderBy('position')->pluck('name', 'id')->all(),
            'duplicates' => $this->form->possibleDuplicates(),
        ])->title($this->form->student?->exists ? $this->form->student->fullName() : __('Add student'));
    }
}
