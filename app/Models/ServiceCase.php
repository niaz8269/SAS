<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** One scholarship application SAS supports for a student, with its status, counsellor and fees. */
class ServiceCase extends Model
{
    use AuditsChanges;

    public const STATUSES = [
        'new' => 'New',
        'in_progress' => 'In progress',
        'submitted' => 'Submitted to the funder',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'withdrawn' => 'Withdrawn',
        'closed' => 'Closed',
    ];

    /** Where a case may go next. */
    public const NEXT = [
        'new' => ['in_progress', 'withdrawn'],
        'in_progress' => ['submitted', 'withdrawn'],
        'submitted' => ['accepted', 'rejected', 'withdrawn'],
        'accepted' => ['closed'],
        'rejected' => ['closed'],
        'withdrawn' => ['closed'],
        'closed' => [],
    ];

    // The student, advisor and status are set by trusted code (CaseService), never from request data.
    protected $guarded = ['id', 'public_id', 'user_id', 'advisor_id', 'status', 'awaiting_reply', 'form_shared_at', 'form_consent_text'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'outcome_at' => 'datetime', 'awaiting_reply' => 'boolean', 'form_shared_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            $c->public_id ??= (string) Str::ulid();
            $c->opened_at ??= now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ScholarshipCycle::class, 'cycle_id');
    }

    public function degreeLevel(): BelongsTo
    {
        return $this->belongsTo(DegreeLevel::class, 'degree_level_id');
    }

    /** Whether this staff member may read the student's master form: the student's own counsellor always, anyone else only where the student shared it on this case (older cases). */
    public function formReadableBy(User $viewer): bool
    {
        return $this->form_shared_at !== null || $viewer->counsels($this->student);
    }

    public function sharedForm(): ?ApplicationForm
    {
        return ApplicationForm::where('user_id', $this->user_id)->first();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'case_id')->orderBy('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CaseMessage::class, 'case_id')->orderBy('id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(CaseDocumentShare::class, 'case_id')->whereNull('revoked_at')->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
