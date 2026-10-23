<?php

namespace App\Livewire\Students;

use App\Models\SkillLevel;
use App\Models\Student;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** All students as sedcards, with search (US-17). Staff and owners. */
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'level', except: '')]
    public string $level = '';

    #[Url(as: 'archived', except: false)]
    public bool $showArchived = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'level', 'showArchived'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $students = Student::query()
            ->with('skillLevel')
            ->when(! $this->showArchived, fn ($q) => $q->active())
            ->when($this->showArchived, fn ($q) => $q->whereNotNull('archived_at'))
            ->when($this->level !== '', fn ($q) => $q->where('skill_level_id', (int) $this->level))
            ->search($this->search)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(24);

        return view('livewire.students.index', [
            'students' => $students,
            'levels' => SkillLevel::query()->orderBy('position')->get(),
            'today' => auth()->user()->school->today(),
        ])->title(__('Students'));
    }
}
