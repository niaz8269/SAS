<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationTask extends Model
{
    protected $guarded = ['id', 'plan_id'];

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
            'requirement_updated' => 'boolean',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ApplicationPlan::class, 'plan_id');
    }
}
