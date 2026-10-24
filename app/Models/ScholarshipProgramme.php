<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ScholarshipProgramme extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function funder(): BelongsTo
    {
        return $this->belongsTo(Funder::class);
    }

    public function hostCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'host_country_id');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(ScholarshipCycle::class, 'programme_id');
    }

    public function documentRules(): HasMany
    {
        return $this->hasMany(DocumentRule::class, 'programme_id');
    }

    public function universities(): BelongsToMany
    {
        return $this->belongsToMany(University::class, 'programme_university', 'programme_id');
    }

    /**
     * The photo for a scholarship card or page: the programme's own picture from the library, else a bundled photo made for this programme
     * (images/programmes/<slug>.jpg), else one of the destination country's photos (of the cycle, then the host country), chosen by the
     * programme's number so scholarships of one country take turns.
     */
    public function imageUrl(?ScholarshipCycle $cycle = null): string
    {
        if ($this->image_path) {
            return Storage::disk('public_media')->url($this->image_path);
        }

        return asset($this->bundledImage($cycle));
    }

    /** The bundled file (relative to public/) behind imageUrl(), also used to find its credit. */
    public function bundledImage(?ScholarshipCycle $cycle = null): string
    {
        $own = 'images/programmes/'.$this->slug.'.jpg';
        if (file_exists(public_path($own))) {
            return $own;
        }
        $country = $cycle?->destinations->first() ?? $this->hostCountry;

        return $country ? $country->galleryFile((int) $this->id) : 'images/destinations/default.jpg';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
