<?php

namespace App\Policies;

use App\Models\PartnerSubmission;
use App\Models\User;

/** Partners see and edit only their own organisation's submissions, and only until editors decide. Staff need partners.manage. */
class PartnerSubmissionPolicy
{
    private function partnerOf(User $user, PartnerSubmission $s): bool
    {
        return $user->isActive() && $user->hasRole('partner_manager') && $user->partner_organisation_id !== null
            && $user->partner_organisation_id === $s->organisation_id && $s->organisation?->isActive();
    }

    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission('partners.manage');
    }

    public function view(User $user, PartnerSubmission $s): bool
    {
        return $this->partnerOf($user, $s) || ($user->isStaff() && $user->hasPermission('partners.manage'));
    }

    public function update(User $user, PartnerSubmission $s): bool
    {
        return ($this->partnerOf($user, $s) && $s->editableByPartner()) || ($user->isStaff() && $user->hasPermission('partners.manage'));
    }

    public function delete(User $user, PartnerSubmission $s): bool
    {
        return $user->isStaff() && $user->hasPermission('partners.manage');
    }
}
