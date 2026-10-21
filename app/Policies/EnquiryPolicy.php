<?php

namespace App\Policies;

use App\Models\Enquiry;
use App\Models\User;

/** COM-01 / AUTH-04: students see their own; staff see all only with enquiries.view_all, otherwise only assigned. */
class EnquiryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && ($user->hasPermission('enquiries.view_all') || $user->hasPermission('enquiries.assigned'));
    }

    public function view(User $user, Enquiry $enquiry): bool
    {
        if (! $user->isActive()) {
            return false;
        }
        if ($enquiry->user_id === $user->id) {
            return true;
        }

        return $user->hasPermission('enquiries.view_all')
            || ($user->hasPermission('enquiries.assigned') && $enquiry->owner_id === $user->id);
    }

    public function update(User $user, Enquiry $enquiry): bool
    {
        return $user->isStaff() && $this->view($user, $enquiry) && $enquiry->user_id !== $user->id;
    }

    public function assign(User $user): bool
    {
        return $user->hasPermission('enquiries.assign');
    }
}
