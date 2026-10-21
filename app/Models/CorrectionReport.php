<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectionReport extends Model
{
    protected $guarded = ['id'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ScholarshipCycle::class, 'cycle_id');
    }
}
