<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A saved reply. Shared ones (no owner) are for everyone who counsels; the rest belong to one counsellor. */
class ReplyTemplate extends Model
{
    protected $guarded = ['id', 'owner_id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** The templates a staff member may use: the shared ones and their own. */
    public function scopeUsableBy(Builder $q, User $user): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('owner_id')->orWhere('owner_id', $user->id));
    }

    public function fill_for(User $student, User $counsellor): string
    {
        return strtr($this->body, ['{name}' => $student->name, '{counsellor}' => $counsellor->name]);
    }
}
