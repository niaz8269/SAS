<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A scholarship open to international students at one university (university, provincial, city or China Scholarship Council funded). */
class UniversityScholarship extends Model
{
    public const PROVIDERS = ['csc' => 'Chinese Government (CSC)', 'provincial' => 'Provincial government', 'city' => 'City government', 'university' => 'The university'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'checked_on' => 'date'];
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public function providerLabel(): string
    {
        return self::PROVIDERS[$this->provider] ?? $this->provider;
    }
}
