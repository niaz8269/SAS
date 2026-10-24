<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message from SAS to a group of students: everyone, or the people who match one condition. */
class Announcement extends Model
{
    public const AUDIENCES = [
        'all' => 'Every student',
        'programme' => 'Students with an application to one scholarship',
        'deadline' => 'Students with an unfinished application closing within some days',
        'agent' => 'Students of one recruiting agency',
        'counsellor' => 'Students of one counsellor',
        'journey' => 'Students whose application is at one stage of the journey',
    ];

    protected $guarded = ['id', 'created_by', 'recipients', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }
}
