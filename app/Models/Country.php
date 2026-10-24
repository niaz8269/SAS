<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Country extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_destination' => 'boolean',
            'guidance_published' => 'boolean',
            'featured' => 'boolean',
            'cost_notes' => 'array',
            'guidance_sources' => 'array',
            'official_languages' => 'array',
            'guidance_reviewed_at' => 'date',
        ];
    }

    /** @return list<string> the country's official language(s): staff-set, else config/languages.php, else none known */
    public function officialLanguages(): array
    {
        return array_values($this->official_languages ?: config('languages.official.'.strtoupper((string) $this->code), []));
    }

    public function scopeDestinations(Builder $q): Builder
    {
        return $q->where('is_destination', true)->orderBy('sort')->orderBy('name');
    }

    /** The staff-chosen photo, else a bundled illustration of the country's landmark, else a generic globe. */
    public function imageUrl(): string
    {
        if ($this->image_path) {
            return Storage::disk('public_media')->url($this->image_path);
        }
        $file = 'images/destinations/'.strtolower((string) $this->code).'.jpg';

        return asset(file_exists(public_path($file)) ? $file : 'images/destinations/default.jpg');
    }

    /**
     * The bundled photos of the country, the main one first (cn.jpg, then cn-2.jpg ...; China also lends its city photos).
     * Scholarship cards take turns through them so two scholarships of one country do not look alike.
     *
     * @return list<string> paths relative to public/
     */
    public function galleryFiles(): array
    {
        $code = strtolower((string) $this->code);
        $files = [];
        foreach (array_merge(["$code.jpg"], array_map('basename', glob(public_path("images/destinations/$code-*.jpg")) ?: [])) as $name) {
            if (file_exists(public_path("images/destinations/$name"))) {
                $files[] = "images/destinations/$name";
            }
        }
        $main = array_shift($files);
        natsort($files);
        $files = array_merge($main ? [$main] : [], array_values($files));
        if ($code === 'cn') {
            foreach (glob(public_path('images/cities/*.jpg')) ?: [] as $city) {
                $files[] = 'images/cities/'.basename($city);
            }
        }

        return $files;
    }

    /** The relative file a scholarship card shows for this country: the gallery photo for the scholarship's place in the country's list, else the default picture. */
    public function galleryFile(int $programmeId): string
    {
        $files = $this->galleryFiles();
        if (! $files) {
            return 'images/destinations/default.jpg';
        }
        // The scholarships of this country in a fixed order, so neighbouring ones get different photos (worked out once per request).
        $key = 'country-programmes-'.$this->getKey();
        $load = fn () => ScholarshipProgramme::where('host_country_id', $this->getKey())
            ->orWhereHas('cycles.destinations', fn ($q) => $q->whereKey($this->getKey()))->orderBy('id')->pluck('id')->all();
        $ids = Cache::driver('array')->rememberForever($key, $load);
        $rank = array_search($programmeId, $ids, true);
        if ($rank === false) { // a scholarship added since the list was made
            $ids = $load();
            Cache::driver('array')->forever($key, $ids);
            $rank = array_search($programmeId, $ids, true);
        }

        return $files[($rank === false ? $programmeId : $rank) % count($files)];
    }

    /** Credit line for the picture shown: the bundled photo's photographer, or the credit saved with the library file. @return array{name:string,url:?string}|null */
    public function imageCredit(): ?array
    {
        if ($this->image_path) {
            $credit = MediaFile::where('path', $this->image_path)->value('credit');

            return $credit ? ['name' => $credit, 'url' => null] : null;
        }
        $file = 'images/destinations/'.strtolower((string) $this->code).'.jpg';
        $credits = config('sas.image_credits');

        return $credits[file_exists(public_path($file)) ? $file : 'images/destinations/default.jpg'] ?? null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
