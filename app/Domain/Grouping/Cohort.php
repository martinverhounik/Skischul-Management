<?php

namespace App\Domain\Grouping;

use App\Enums\TimeslotKey;
use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\Sport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * All students of one day, time, sport and age group (the counting level
 * the owner chose), with the groups formed inside it.
 */
final class Cohort
{
    /** @var Collection<int, GroupEntry> */
    public Collection $entries;

    /** @var Collection<int, Group> */
    public Collection $groups;

    public function __construct(
        public CarbonImmutable $date,
        public TimeslotKey $timeslot,
        public Sport $sport,
        public AgeGroup $ageGroup,
        /** Smallest planned group size of the courses in this cohort. */
        public int $maxParticipants,
    ) {
        $this->entries = collect();
        $this->groups = collect();
    }

    public function key(): string
    {
        return self::makeKey($this->timeslot, $this->sport->id, $this->ageGroup->id);
    }

    public static function makeKey(TimeslotKey $timeslot, int $sportId, int $ageGroupId): string
    {
        return "{$timeslot->value}-{$sportId}-{$ageGroupId}";
    }

    public function studentCount(): int
    {
        return $this->entries->count();
    }

    /** US-22: how many instructors this cohort needs, from the planned group size. */
    public function instructorsNeeded(): int
    {
        return (int) ceil($this->studentCount() / max(1, $this->maxParticipants));
    }

    public function instructorsAssigned(): int
    {
        return $this->groups->whereNotNull('instructor_id')->count();
    }

    /** @return Collection<int, GroupEntry> */
    public function unassigned(): Collection
    {
        return $this->entries->filter(fn (GroupEntry $e) => $e->groupId === null)->values();
    }

    /** @return Collection<int, GroupEntry> */
    public function entriesOf(Group $group): Collection
    {
        return $this->entries->filter(fn (GroupEntry $e) => $e->groupId === $group->id)->values();
    }

    /** Skill level name => number of students, beginner first (information only). */
    public function levelCounts(): Collection
    {
        return $this->entries
            ->sortBy(fn (GroupEntry $e) => $e->student->skillLevel?->position ?? 999)
            ->groupBy(fn (GroupEntry $e) => $e->student->skillLevel?->name ?? '–')
            ->map->count();
    }
}
