<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseDocumentShare extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['shared_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class, 'case_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
