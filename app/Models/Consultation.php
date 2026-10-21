<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Consultation extends Model
{
    protected $guarded = ['id', 'user_id', 'status', 'advisor_id', 'confirmed_start_utc'];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            $c->public_id ??= (string) Str::ulid();
            $c->reference ??= 'CON-'.strtoupper(Str::random(8));
        });
    }

    protected function casts(): array
    {
        return [
            'preferred_from' => 'date',
            'preferred_to' => 'date',
            'confirmed_start_utc' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
