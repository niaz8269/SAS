<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An agency's request to become a partner. Only an administrator can approve it. */
class PartnerApplication extends Model
{
    public const YEARS = ['under_1' => 'Less than 1 year', '1_2' => '1 to 2 years', '3_5' => '3 to 5 years', '6_10' => '6 to 10 years', 'over_10' => 'More than 10 years'];

    public const STUDENTS = ['0_20' => 'Up to 20', '21_50' => '21 to 50', '51_100' => '51 to 100', 'over_100' => 'More than 100'];

    protected $guarded = ['id', 'status', 'reviewed_by', 'reviewed_at', 'partner_organisation_id', 'user_id'];

    protected function casts(): array
    {
        return ['consent_given' => 'boolean', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(PartnerOrganisation::class, 'partner_organisation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
