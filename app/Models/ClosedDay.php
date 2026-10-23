<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single date the school is closed (US-01).
 *
 * @property int $id
 * @property Carbon $date
 * @property string|null $note
 */
class ClosedDay extends Model
{
    use BelongsToSchool;

    protected $fillable = ['date', 'note'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
