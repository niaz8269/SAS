<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleRevision extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'override' => 'boolean', 'decided_at' => 'datetime'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ScholarshipCycle::class, 'cycle_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }
}
