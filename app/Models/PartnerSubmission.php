<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PartnerSubmission extends Model
{
    use AuditsChanges;

    public const STATUSES = ['submitted' => 'Submitted', 'in_review' => 'Being checked', 'needs_changes' => 'Needs changes', 'accepted' => 'Accepted', 'rejected' => 'Not accepted'];

    public const FUNDING = ['full' => 'Full funding', 'partial' => 'Partial funding', 'tuition_only' => 'Tuition only', 'stipend_only' => 'Stipend only', 'other' => 'Other', 'unknown' => 'Not sure'];

    // Organisation, author, status and the staff notes are set by trusted code, never from the partner's form.
    protected $guarded = ['id', 'public_id', 'organisation_id', 'submitted_by', 'status', 'editor_note', 'internal_note', 'cycle_id'];

    protected function casts(): array
    {
        return ['degree_codes' => 'array', 'deadline_on' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $s) => $s->public_id ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(PartnerOrganisation::class, 'organisation_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ScholarshipCycle::class, 'cycle_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** The partner may change a submission only until editors accept or reject it. */
    public function editableByPartner(): bool
    {
        return in_array($this->status, ['submitted', 'needs_changes'], true);
    }
}
