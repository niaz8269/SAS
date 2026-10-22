<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class HomeSection extends Model
{
    use AuditsChanges;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'items' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('home.sections'));
        static::deleted(fn () => Cache::forget('home.sections'));
    }

    /** Sections keyed by their key, in display order. Disabled sections are kept so the page can skip them. */
    public static function ordered(): Collection
    {
        return Cache::remember('home.sections', 300, fn () => static::query()->orderBy('sort')->orderBy('id')->get())->keyBy('key');
    }
}
