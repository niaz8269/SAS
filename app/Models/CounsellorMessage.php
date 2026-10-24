<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message between a student and their counsellor. It is from the student when the author is the student. */
class CounsellorMessage extends Model
{
    protected $guarded = ['id', 'student_id', 'author_id'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function fromStudent(): bool
    {
        return $this->author_id === $this->student_id;
    }
}
