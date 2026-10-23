<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
            'guidance_reviewed_at' => 'date',
        ];
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
