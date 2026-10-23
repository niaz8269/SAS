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

    private function bundledImage(): string
    {
        $city = 'images/cities/'.Str::slug((string) $this->province).'.jpg';

        return file_exists(public_path($city)) ? $city : 'images/destinations/cn.jpg';
    }

    /** @return array{name:string,url:?string,subject:string}|null */
    public function imageCredit(): ?array
    {
        if ($this->image_path) {
            $credit = MediaFile::where('path', $this->image_path)->value('credit');

            return $credit ? ['name' => $credit, 'url' => null, 'subject' => $this->name] : null;
        }

        return config('sas.image_credits')[$this->bundledImage()] ?? null;
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
