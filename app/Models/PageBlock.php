<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PageBlock extends Model
{
    use AuditsChanges;

    public const PAGES = ['directory' => 'Scholarship directory', 'destinations' => 'Study destinations', 'services' => 'Our services', 'resources' => 'Resources', 'contact' => 'Contact', 'universities' => 'Universities', 'programmes' => 'Programme search'];

    /** Built-in block keys per page; they can be edited or hidden but not deleted. */
    public const BUILT_IN = ['intro', 'empty', 'aside'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'items' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('page.blocks'));
        static::deleted(fn () => Cache::forget('page.blocks'));
    }

    public function isBuiltIn(): bool
    {
        return in_array($this->key, self::BUILT_IN, true);
    }

    /** @return Collection<int, PageBlock> every block of one page, in order */
    public static function forPage(string $page): Collection
    {
        try {
            // Plain arrays in the cache (it never unserialises Eloquent objects); models are rebuilt here.
            $rows = Cache::get('page.blocks');
            if ($rows === null) {
                $rows = static::query()->orderBy('sort')->orderBy('id')->get()->map->getAttributes()->groupBy('page')->map->all()->all();
                Cache::put('page.blocks', $rows, 300);
            }
        } catch (\Throwable) {
            return collect(); // not migrated yet
        }

        return static::hydrate($rows[$page] ?? []);
    }

    /** A built-in block, or null when it was hidden or removed (callers fall back to the original wording). */
    public static function builtIn(string $page, string $key): ?self
    {
        return static::forPage($page)->first(fn ($b) => $b->key === $key);
    }

    /** Staff-added blocks to show above or below the main content of a page. */
    public static function added(string $page, string $position): Collection
    {
        return static::forPage($page)->filter(fn ($b) => $b->enabled && ! $b->isBuiltIn() && $b->position === $position)->values();
    }
}
