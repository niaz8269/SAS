<?php

namespace App\Policies;

use App\Models\PartnerOrganisation;
use App\Models\User;

class PartnerOrganisationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission('partners.manage');
    }

    public function view(User $user, PartnerOrganisation $o): bool
    {
        return $this->viewAny($user) || ($user->partner_organisation_id === $o->id && $user->hasRole('partner_manager'));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PartnerOrganisation $o): bool
    {
        return $this->viewAny($user) || ($user->partner_organisation_id === $o->id && $user->hasRole('partner_manager') && $o->isActive());
    }

    public function delete(User $user, PartnerOrganisation $o): bool
    {
        return $this->viewAny($user);
    }
}
