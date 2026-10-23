<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseMessage extends Model
{
    // Case, author and visibility are set by CaseService, never from request data.
    protected $guarded = ['id', 'case_id', 'author_id', 'visibility'];

    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class, 'case_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
