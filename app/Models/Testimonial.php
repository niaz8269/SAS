<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use AuditsChanges;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['consent_given' => 'boolean', 'published' => 'boolean', 'consent_date' => 'date'];
    }

    protected static function booted(): void
    {
        // Whatever the form or any other code does, a quote without recorded consent can never be public.
        static::saving(function (self $t) {
            if (! $t->consent_given || ! $t->consent_date) {
                $t->published = false;
            }
        });
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('published', true)->where('consent_given', true)->whereNotNull('consent_date');
    }
}
