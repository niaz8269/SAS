<?php

namespace App\Models;

use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApplicationPlan extends Model
{
    /** The student's stage with SAS: draft (still filling in) > submitted (waiting for staff) > returned (fix and resend) or passed. */
    public const STAGES = ['draft' => 'Unfinished', 'submitted' => 'Submitted for review', 'returned' => 'Returned', 'passed' => 'Review passed'];

    protected $attributes = ['stage' => 'draft'];

    protected $guarded = ['id', 'user_id', 'review_requested_at', 'review_enquiry_id', 'stage', 'review_remarks', 'reviewed_at', 'reviewed_by', 'application_no'];

    protected static function booted(): void
    {
        static::creating(function (self $plan) {
            $plan->public_id ??= (string) Str::ulid();
            $plan->application_no ??= 'SAS'.date('y').str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'submitted_at' => 'datetime',
            'requirements_changed_at' => 'datetime',
            'review_requested_at' => 'datetime',
            'journey_stage_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** True while the student or agent may still change the application: not yet sent, or sent back for fixes. */
    public function isEditable(): bool
    {
        return in_array($this->stage, ['draft', 'returned'], true);
    }

    /** What the student and agent see: the review stage, or once passed the status of SAS's own case for it. */
    public function statusFor(?ServiceCase $case): string
    {
        return $this->stage === 'passed' && $case ? 'Review passed. '.$case->statusLabel() : $this->stageLabel();
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? $this->stage;
    }

    public function documentReasons(): HasMany
    {
        return $this->hasMany(ApplicationDocumentReason::class, 'plan_id');
    }

    /** Opaque identifier in URLs (UAT-07); internal ids are never exposed. */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function journeyTasks(): HasMany
    {
        return $this->hasMany(JourneyTask::class, 'plan_id');
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
