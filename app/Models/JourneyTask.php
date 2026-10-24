<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One thing to do at a stage of an application's journey, with an optional date. */
class JourneyTask extends Model
{
    protected $guarded = ['id', 'plan_id', 'done_at', 'done_by', 'created_by'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'done_at' => 'datetime', 'custom' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ApplicationPlan::class, 'plan_id');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_on !== null && $this->due_on->lt(today());
    }
}
