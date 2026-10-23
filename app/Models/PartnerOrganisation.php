<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerOrganisation extends Model
{
    use AuditsChanges;

    public const KINDS = ['university' => 'University', 'funder' => 'Funder or foundation', 'agent' => 'Recruiting agent', 'other' => 'Other'];

    protected $guarded = ['id', 'status'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'partner_organisation_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PartnerSubmission::class, 'organisation_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
