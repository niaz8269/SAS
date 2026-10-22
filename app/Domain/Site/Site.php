<?php

namespace App\Domain\Site;

use App\Models\MenuItem;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Staff-editable site values. Every value falls back to config/sas.php, so an empty setting never breaks a page.
 * Cached; saving through the admin calls forget().
 */
class Site
{
    /** key => [label, config path it overrides (or null)] */
    public const FIELDS = [
        'brand_name' => 'sas.brand.name',
        'brand_short' => 'sas.brand.short',
        'tagline' => 'sas.brand.tagline',
        'legal_entity' => 'sas.legal_entity',
        'contact_email' => 'sas.contact_email',
        'accessibility_email' => 'sas.accessibility_email',
        'contact_phone' => null,
        'contact_address' => null,
        'footer_note' => null,
        'default_meta_description' => null,
        'logo_path' => null,
        'social_facebook' => null,
        'social_x' => null,
        'social_instagram' => null,
        'social_linkedin' => null,
        'social_youtube' => null,
        'announcement_enabled' => null,
        'announcement_text' => null,
        'announcement_url' => null,
    ];

    public static function all(): array
    {
        try {
            if (($cached = Cache::get('site.settings')) !== null) {
                return $cached;
            }
            $all = SiteSetting::query()->pluck('value', 'key')->all();
            Cache::forever('site.settings', $all);

            return $all;
        } catch (\Throwable) {
            return []; // tables or cache not ready yet (fresh install): nothing is cached
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::all()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function save(array $values): void
    {
        foreach (array_keys(self::FIELDS) as $key) {
            if (array_key_exists($key, $values)) {
                $value = $values[$key];
                SiteSetting::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : ($value === null ? null : trim((string) $value))]);
            }
        }
        self::forget();
        self::applyToConfig();
    }

    public static function forget(): void
    {
        Cache::forget('site.settings');
        Cache::forget('site.menus');
    }

    /** Push edited values into config() so every existing config('sas.…') call reads the live value. */
    public static function applyToConfig(): void
    {
        foreach (self::FIELDS as $key => $path) {
            if ($path && ($value = self::get($key))) {
                config([$path => $value]);
            }
        }
    }

    /** Only same-site paths, http(s), mailto and tel links are ever rendered; anything else becomes "#". */
    public static function href(?string $url): string
    {
        $url = trim((string) $url);

        return preg_match('~^(/(?!/)|https?://|mailto:|tel:)~i', $url) ? $url : '#';
    }

    /** @return list<array{label:string,url:string,new_tab:bool,active:bool}> */
    public static function menu(string $location): array
    {
        try {
            $rows = Cache::get('site.menus');
            if ($rows === null) {
                $rows = MenuItem::query()->where('visible', true)->orderBy('sort')->orderBy('id')->get(['location', 'label', 'url', 'new_tab'])->groupBy('location')
                    ->map(fn ($g) => $g->map(fn ($m) => ['label' => $m->label, 'url' => $m->url, 'new_tab' => (bool) $m->new_tab])->all())->all();
                Cache::forever('site.menus', $rows);
            }
        } catch (\Throwable) {
            $rows = [];
        }

        $current = '/'.trim(request()->path(), '/');
        $current = $current === '/' ? '/' : $current;

        return array_map(function ($item) use ($current) {
            $path = '/'.trim((string) parse_url($item['url'], PHP_URL_PATH), '/');
            $path = $path === '' ? '/' : $path;
            $external = preg_match('~^https?://~i', $item['url']) && ! str_starts_with($item['url'], url('/'));
            $active = ! $external && ($path === '/' ? $current === '/' : ($current === $path || str_starts_with($current, $path.'/')));

            return ['label' => $item['label'], 'url' => self::href($item['url']), 'new_tab' => $item['new_tab'], 'active' => $active];
        }, $rows[$location] ?? []);
    }
}
