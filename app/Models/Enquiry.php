<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Enquiry extends Model
{
    protected $guarded = ['id', 'user_id', 'owner_id', 'status'];

    protected static function booted(): void
    {
        static::creating(function (self $e) {
            $e->public_id ??= (string) Str::ulid();
            $e->reference ??= 'ENQ-'.strtoupper(Str::random(8));
        });
    }

    protected function casts(): array
    {
        return [
            'status' => EnquiryStatus::class,
            'consent_at' => 'datetime',
            'next_action_at' => 'datetime',
            'view_token_expires_at' => 'datetime',
            'first_response_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EnquiryMessage::class)->orderBy('id');
    }

    /** COM-01: internal notes never appear in student responses. */
    public function publicMessages(): HasMany
    {
        return $this->messages()->where('visibility', 'public');
    }
}
