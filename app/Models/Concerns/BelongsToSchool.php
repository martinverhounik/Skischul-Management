<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Use on every model that belongs to one school (tenant).
 *
 * - Queries are filtered to the current school whenever one is set.
 * - New records get the current school's id automatically.
 *
 * Without a current school (console, queue) queries are not filtered, so
 * jobs and commands must call CurrentSchool::runAs() or filter explicitly.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $builder): void {
            $current = app(CurrentSchool::class);

            if ($current->has()) {
                $builder->where($builder->qualifyColumn('school_id'), $current->id());
            }
        });

        static::creating(function ($model): void {
            $current = app(CurrentSchool::class);

            if ($model->school_id === null && $current->has()) {
                $model->school_id = $current->id();
            }
        });
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
