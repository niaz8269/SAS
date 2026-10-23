<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class University extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'catalogue_checked_at' => 'datetime', 'published' => 'boolean'];
    }

    /** Universities shown on the public catalogue pages. */
    public function scopeInCatalogue(Builder $query): Builder
    {
        return $query->where('published', true)->whereNotNull('csc_key');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(UniversityProgramme::class);
    }

    protected static function booted(): void
    {
        // An English name typed in the admin is marked as staff-entered so a later import never overwrites it.
        static::saving(function (self $u) {
            if ($u->exists && $u->isDirty('name') && ! $u->isDirty('name_en_source')) {
                $u->name_en_source = 'staff';
            }
        });
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
