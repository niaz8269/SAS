<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScholarshipRoute extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (self $route) {
            $route->application_domain = $route->application_url
                ? strtolower((string) parse_url($route->application_url, PHP_URL_HOST))
                : null;
        });
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ScholarshipCycle::class, 'cycle_id');
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(CycleDeadline::class, 'route_id');
    }
}
