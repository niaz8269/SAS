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
        return ['verified_at' => 'datetime', 'catalogue_checked_at' => 'datetime', 'published' => 'boolean', 'profile' => 'array', 'profile_checked_at' => 'date'];
    }

    /** Universities shown on the public pages: those on the official study-in-China list, and medical universities that have a checked fee structure and at least one scholarship. */
    /** Staff-placed universities first, in the order they were placed; the rest follow in the order the caller adds. */
    public function scopeManualOrder(Builder $query): Builder
    {
        return $query->orderByRaw('universities.sort_order IS NULL')->orderBy('universities.sort_order');
    }

    public function scopeInCatalogue(Builder $query): Builder
    {
        return $query->where('published', true)->where(fn (Builder $w) => $w->whereNotNull('csc_key')->orWhere(fn (Builder $m) => $m->medicalListed()));
    }

    /** A university with a published fee structure AND a published scholarship for international students. Without a scholarship it is not listed. */
    public function scopeMedicalListed(Builder $query): Builder
    {
        return $query->whereHas('feeStructures', fn ($q) => $q->where('published', true))->whereHas('scholarships', fn ($q) => $q->where('published', true));
    }

    public function isMedicalListed(): bool
    {
        return $this->feeStructures()->where('published', true)->exists() && $this->scholarships()->where('published', true)->exists();
    }

    public function feeStructures(): HasMany
    {
        return $this->hasMany(UniversityFeeStructure::class)->orderBy('sort')->orderBy('id');
    }

    public function scholarships(): HasMany
    {
        return $this->hasMany(UniversityScholarship::class)->orderBy('sort')->orderBy('id');
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
