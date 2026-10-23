<?php

namespace App\Livewire\Instructors;

use App\Models\Instructor;
use App\Models\Sport;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * All instructors (US-20, US-21). Everyone can see the list for planning;
 * editing and the financial columns are for owners only (US-27).
 */
class Index extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sport', except: '')]
    public string $sport = '';

    #[Url(as: 'inactive', except: false)]
    public bool $showInactive = false;

    public function toggleActive(int $id): void
    {
        $this->authorize('admin');

        $instructor = Instructor::query()->findOrFail($id);
        $instructor->update(['is_active' => ! $instructor->is_active]);
    }

    public function render()
    {
        $term = trim($this->search);

        $instructors = Instructor::query()
            ->with('sports')
            ->when(! $this->showInactive, fn ($q) => $q->active())
            ->when($this->sport !== '', fn ($q) => $q->whereHas('sports', fn ($s) => $s->whereKey((int) $this->sport)))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->orderByDesc('is_active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('livewire.instructors.index', [
            'instructors' => $instructors,
            'sports' => Sport::query()->orderBy('position')->get(),
            'isOwner' => auth()->user()->can('admin'),
        ])->title(__('Instructors'));
    }
}
