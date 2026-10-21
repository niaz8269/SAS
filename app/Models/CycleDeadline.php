<?php

namespace App\Models;

use App\Enums\DeadlineType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleDeadline extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_type' => DeadlineType::class,
            'opens_at_utc' => 'datetime',
            'closes_at_utc' => 'datetime',
            'opens_on' => 'date',
            'closes_on' => 'date',
            'superseded_at' => 'datetime',
        ];
    }

    public function scopeCurrent(Builder $q): Builder
    {
        return $q->whereNull('superseded_at');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ScholarshipCycle::class, 'cycle_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(ScholarshipRoute::class, 'route_id');
    }
}
