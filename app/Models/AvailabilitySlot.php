<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A time a counsellor is free for a call. */
class AvailabilitySlot extends Model
{
    protected $guarded = ['id', 'counsellor_id', 'booked_by', 'consultation_id', 'booked_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'booked_at' => 'datetime'];
    }

    public function counsellor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counsellor_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function isBooked(): bool
    {
        return $this->booked_by !== null;
    }

    public function scopeFree(Builder $q): Builder
    {
        return $q->whereNull('booked_by')->where('starts_at', '>', now()->addHours(2)); // a slot is not offered in the last two hours
    }
}
