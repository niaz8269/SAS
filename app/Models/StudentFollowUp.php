<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One staff-only update about a student: their status, what they want and the counsellor's comment. The newest one is the current state. */
class StudentFollowUp extends Model
{
    /** status key => label, in the order a student usually moves through them. */
    public const STATUSES = [
        'new' => 'New student',
        'contacted' => 'Contacted',
        'documents' => 'Collecting documents',
        'applying' => 'Preparing applications',
        'submitted' => 'Applications submitted',
        'offer' => 'Offer or admission received',
        'visa' => 'Visa and travel',
        'on_hold' => 'On hold',
        'not_interested' => 'Not interested',
        'closed' => 'Completed',
    ];

    // Student and author are set by Counselling, never from request data.
    protected $guarded = ['id', 'student_id', 'author_id'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
