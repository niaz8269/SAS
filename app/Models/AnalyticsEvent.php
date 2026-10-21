<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /** ANA-01: events carry a name, an optional cycle id and non-personal metadata only. Never free text, files or contact details. */
    public static function track(string $name, ?int $cycleId = null, array $meta = []): void
    {
        static::create(['name' => $name, 'cycle_id' => $cycleId, 'meta' => $meta ?: null]);
    }

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }
}
