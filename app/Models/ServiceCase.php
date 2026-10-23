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
    protected $guarded = ['id', 'public_id', 'user_id', 'advisor_id', 'status'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'outcome_at' => 'datetime'];
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'case_id')->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
