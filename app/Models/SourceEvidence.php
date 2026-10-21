<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceEvidence extends Model
{
    protected $table = 'source_evidence';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ScholarshipCycle::class, 'cycle_id');
    }
}
