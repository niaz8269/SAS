<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A staff comment on one document; the student sees it next to the file. */
class DocumentComment extends Model
{
    protected $guarded = ['id', 'document_id', 'author_id'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
