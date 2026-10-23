<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerOrganisation extends Model
{
    use AuditsChanges;

    public const KINDS = ['agent' => 'Recruiting agent'];

    protected $attributes = ['kind' => 'agent'];

    protected $guarded = ['id', 'status'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'partner_organisation_id');
    }

    public function isAgent(): bool
    {
        return $this->kind === 'agent';
    }

    /** Students this recruiting agent looks after. */
    public function students(): HasMany
    {
        return $this->hasMany(User::class, 'managed_by_organisation_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
