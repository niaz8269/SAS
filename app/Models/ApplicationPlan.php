<?php

namespace App\Models;

use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApplicationPlan extends Model
{
    protected $guarded = ['id', 'user_id', 'review_requested_at', 'review_enquiry_id'];

    protected static function booted(): void
    {
        static::creating(function (self $plan) {
            $plan->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'submitted_at' => 'datetime',
            'requirements_changed_at' => 'datetime',
            'review_requested_at' => 'datetime',
        ];
    }

    /** Opaque identifier in URLs (UAT-07); internal ids are never exposed. */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ScholarshipCycle::class, 'cycle_id');
    }

    public function degreeLevel(): BelongsTo
    {
        return $this->belongsTo(DegreeLevel::class, 'degree_level_id');
    }

    public function reviewEnquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class, 'review_enquiry_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ApplicationTask::class, 'plan_id')->orderBy('id');
    }
}
