<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'deliverables' => 'array',
            'exclusions' => 'array',
            'required_inputs' => 'array',
            'process_steps' => 'array',
            'is_available' => 'boolean',
            'published' => 'boolean',
            'featured' => 'boolean',
        ];
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('published', true)->orderBy('sort')->orderBy('name');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** PUB-05: no invented prices. */
    public function priceLabel(): string
    {
        return $this->price_basis ?: 'Request a quotation';
    }
}
