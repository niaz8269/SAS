<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CmsPage extends Model
{
    use AuditsChanges;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('sitemap'));
        static::deleted(fn () => Cache::forget('sitemap'));
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
