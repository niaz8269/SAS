<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleMilestone extends Model
{
    public const KINDS = ['opens' => 'Applications open', 'interview' => 'Interviews', 'test' => 'Tests or assessments', 'results' => 'Final results', 'other' => 'Other date'];

    public const DATE_TYPES = ['exact' => 'Exact date', 'date_only' => 'Date (no time)', 'month' => 'Month only', 'range' => 'Date range', 'not_announced' => 'Not announced', 'route_dependent' => 'Depends on country or route'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ScholarshipCycle::class, 'cycle_id');
    }

    /** What to show: exactly what the source supports, never a guessed date or time. */
    public function display(): string
    {
        $fmt = fn (?CarbonImmutable $d, string $f) => $d?->format($f);
        $time = $this->time_text ? ', '.$this->time_text : '';

        // Month and range entries show the source's own phrase when there is one (for example "mid-October 2026 to January 2027"),
        // so no day is implied that the source did not give.
        if (in_array($this->date_type, ['month', 'range'], true) && filled($this->time_text)) {
            return $this->time_text;
        }

        return match ($this->date_type) {
            'exact', 'date_only' => ($this->starts_on ? $fmt($this->starts_on->toImmutable(), 'j F Y') : 'Not announced').$time,
            'month' => $this->starts_on ? $fmt($this->starts_on->toImmutable(), 'F Y') : 'Not announced',
            'range' => $this->starts_on && $this->ends_on ? $this->starts_on->format('j F Y').' to '.$this->ends_on->format('j F Y') : ($this->starts_on?->format('j F Y') ?? 'Not announced'),
            'route_dependent' => 'Depends on your country or route',
            default => 'Not announced yet',
        };
    }
}
