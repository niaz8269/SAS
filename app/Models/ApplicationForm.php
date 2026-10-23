<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A student's master application form (modelled on the CSC form). Everything is stored encrypted as one JSON document; only the student and, once shared on a case, that case's staff can read it. */
class ApplicationForm extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return ['data' => 'encrypted:array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return ($this->data ?? [])[$key] ?? $default;
    }
}
