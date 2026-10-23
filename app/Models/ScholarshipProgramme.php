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

    public function universities(): BelongsToMany
    {
        return $this->belongsToMany(University::class, 'programme_university', 'programme_id');
    }

    /** The photo for a scholarship card or page: the programme's own picture, else the photo of its destination country (of the cycle, then the host country). */
    public function imageUrl(?ScholarshipCycle $cycle = null): string
    {
        if ($this->image_path) {
            return Storage::disk('public_media')->url($this->image_path);
        }
        $country = $cycle?->destinations->first() ?? $this->hostCountry;

        return ($country ?? new Country)->imageUrl();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
