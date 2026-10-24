<?php

namespace App\Domain\Calendar;

use Illuminate\Support\Collection;

/**
 * Places the timed entries of one day side by side when they overlap, like
 * a paper planner: each entry gets a lane, and every entry in a cluster of
 * overlapping entries shares the cluster's lane count (its width).
 */
class DayLayout
{
    /**
     * @param  Collection<int, CalendarEntry>  $entries  timed entries of one day
     * @return list<array{entry: CalendarEntry, lane: int, lanes: int}>
     */
    public static function lanes(Collection $entries): array
    {
        $sorted = $entries->sortBy([
            fn ($a, $b) => $a->startMinutes() <=> $b->startMinutes(),
            fn ($a, $b) => $b->endMinutes() <=> $a->endMinutes(),
        ])->values();

        $out = [];
        $cluster = [];
        $laneEnds = [];
        $clusterEnd = -1;

        $close = function () use (&$out, &$cluster, &$laneEnds) {
            $lanes = max(1, count($laneEnds));
            foreach ($cluster as $item) {
                $out[] = $item + ['lanes' => $lanes];
            }
            $cluster = [];
            $laneEnds = [];
        };

        foreach ($sorted as $entry) {
            if ($entry->startMinutes() >= $clusterEnd && $cluster !== []) {
                $close();
            }
            $lane = null;
            foreach ($laneEnds as $i => $end) {
                if ($end <= $entry->startMinutes()) {
                    $lane = $i;
                    break;
                }
            }
            $lane ??= count($laneEnds);
            $laneEnds[$lane] = $entry->endMinutes();
            $cluster[] = ['entry' => $entry, 'lane' => $lane];
            $clusterEnd = max($clusterEnd, $entry->endMinutes());
        }
        if ($cluster !== []) {
            $close();
        }

        return $out;
    }
}
