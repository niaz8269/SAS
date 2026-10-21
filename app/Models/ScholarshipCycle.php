<?php

namespace App\Models;

use App\Domain\Scholarship\DeadlineService;
use App\Enums\ApplicationStatus;
use App\Enums\PublicationState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScholarshipCycle extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $guarded = ['id'];

    /** Mirror the column defaults so a freshly created model behaves like a reloaded one. */
    protected $attributes = [
        'publication_state' => 'draft',
        'application_status' => 'deadline_unconfirmed',
        'funding_type' => 'unknown',
        'delivery_mode' => 'unknown',
    ];

    protected function casts(): array
    {
        return [
            'publication_state' => PublicationState::class,
            'application_status' => ApplicationStatus::class,
            'next_closing_sort' => 'datetime',
            'fully_funded_verified' => 'boolean',
            'any_nationality' => 'boolean',
            'featured' => 'boolean',
            'sponsored' => 'boolean',
            'last_verified_at' => 'datetime',
            'review_due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** Public visibility: published and not archived (DIR-03). Never leaks drafts. */
    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('publication_state', PublicationState::Published->value);
    }

    public function isPublished(): bool
    {
        return $this->publication_state === PublicationState::Published;
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgramme::class, 'programme_id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(ScholarshipRoute::class, 'cycle_id')->orderBy('sort');
    }

    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'cycle_destinations', 'cycle_id', 'country_id');
    }

    public function nationalities(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'cycle_nationalities', 'cycle_id', 'country_id');
    }

    public function degreeLevels(): BelongsToMany
    {
        return $this->belongsToMany(DegreeLevel::class, 'cycle_degree_levels', 'cycle_id');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'cycle_subjects', 'cycle_id');
    }

    public function universities(): BelongsToMany
    {
        return $this->belongsToMany(University::class, 'cycle_universities', 'cycle_id');
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(CycleBenefit::class, 'cycle_id')->orderBy('sort');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(CycleRequirement::class, 'cycle_id')->orderBy('sort');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(CycleCriterion::class, 'cycle_id');
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(CycleDeadline::class, 'cycle_id');
    }

    public function currentDeadlines(): HasMany
    {
        return $this->hasMany(CycleDeadline::class, 'cycle_id')->whereNull('superseded_at')->orderBy('id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(SourceEvidence::class, 'cycle_id')->latest('checked_at');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CycleReview::class, 'cycle_id')->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function title(): string
    {
        return $this->programme->title;
    }

    /** DIR-04: "Fully funded" only when the covered benefits have been explicitly verified. */
    public function fundingLabel(): string
    {
        return match (true) {
            $this->funding_type === 'full' && $this->fully_funded_verified => 'Fully funded',
            $this->funding_type === 'partial' => 'Partial funding',
            $this->funding_type === 'tuition_only' => 'Tuition covered',
            $this->funding_type === 'stipend_only' => 'Stipend only',
            default => 'Funding details not confirmed',
        };
    }

    public function label(): string
    {
        return trim($this->programme->title.' '.$this->cycle_key);
    }

    /** Status computed at read time from current deadlines (DEAD-03), not trusted from the stored column alone. */
    public function effectiveStatus(): ApplicationStatus
    {
        return app(DeadlineService::class)->statusFor($this);
    }

    public function needsRecheck(): bool
    {
        return $this->isPublished() && $this->review_due_at !== null && $this->review_due_at->isPast();
    }

    public function requirementsHash(): string
    {
        return hash('sha256', $this->requirements->map(fn ($r) => $r->id.'|'.$r->text.'|'.$r->conditional_rule)->implode("\n"));
    }
}
