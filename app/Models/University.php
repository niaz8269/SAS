<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class University extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'catalogue_checked_at' => 'datetime'];
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(UniversityProgramme::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
