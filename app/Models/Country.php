<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
