<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message between a recruiting agency and SAS staff about one student the agency looks after. */
class AgentMessage extends Model
{
    protected $guarded = ['id', 'student_id', 'organisation_id', 'author_id', 'from_agent'];

    protected function casts(): array
    {
        return ['from_agent' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
