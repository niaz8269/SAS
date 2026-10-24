<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Document extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected static function booted(): void
    {
        static::creating(fn (self $d) => $d->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DocumentComment::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function associations(): HasMany
    {
        return $this->hasMany(DocumentAssociation::class);
    }
}
