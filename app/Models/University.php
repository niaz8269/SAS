<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /** The university's own photo, else a photo of its province's best-known city, else the photo of China. */
    public function imageUrl(): string
    {
        if ($this->image_path) {
            return Storage::disk('public_media')->url($this->image_path);
        }

        return asset($this->bundledImage());
    }

    /** Credits for the campus photographs under public/images/universities, keyed by university slug. */
    public static function photoCredits(): array
    {
        static $credits;

        return $credits ??= is_file($p = base_path('data/seed/university-photos.json')) ? (json_decode(file_get_contents($p), true) ?: []) : [];
    }

    /** The university's own photo, else a photo of its province's city, else the photo of its country (never another country's landmark). */
    private function bundledImage(): string
    {
        $own = 'images/universities/'.$this->slug.'.jpg';
        if (file_exists(public_path($own))) {
            return $own;
        }
        $city = 'images/cities/'.Str::slug((string) $this->province).'.jpg';
        if ($this->province && file_exists(public_path($city))) {
            return $city;
        }
        $country = 'images/destinations/'.strtolower((string) $this->country?->code).'.jpg';

        return file_exists(public_path($country)) ? $country : 'images/destinations/default.jpg';
    }

    /** @return array{name:string,url:?string,subject:string}|null */
    public function imageCredit(): ?array
    {
        if ($this->image_path) {
            $credit = MediaFile::where('path', $this->image_path)->value('credit');

            return $credit ? ['name' => $credit, 'url' => null, 'subject' => $this->name] : null;
        }
        if ($c = self::photoCredits()[$this->slug] ?? null) {
            return ['name' => $c['artist'].' ('.$c['license'].')', 'url' => $c['url'], 'subject' => $this->name, 'source' => 'Wikimedia Commons'];
        }
        $image = $this->bundledImage();
        if (str_starts_with($image, 'images/cities/') && ($c = self::cityCredits()[basename($image, '.jpg')] ?? null)) {
            return ['name' => $c['artist'].' ('.$c['license'].')', 'url' => $c['url'], 'subject' => $c['subject'].' (city photo)', 'source' => 'Wikimedia Commons'];
        }

        return config('sas.image_credits')[$this->bundledImage()] ?? null;
    }

    /** Credits for the province skyline photographs from Wikimedia Commons, keyed by province slug. */
    public static function cityCredits(): array
    {
        static $credits;

        return $credits ??= is_file($p = base_path('data/seed/city-photos.json')) ? (json_decode(file_get_contents($p), true) ?: []) : [];
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
