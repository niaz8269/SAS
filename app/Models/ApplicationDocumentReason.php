<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "I cannot provide this document yet", with the reason, for one document on one application. */
class ApplicationDocumentReason extends Model
{
    protected $guarded = ['id'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ApplicationPlan::class, 'plan_id');
    }

    public function kind(): BelongsTo
    {
        return $this->belongsTo(DocumentKind::class, 'kind_id');
    }
}
