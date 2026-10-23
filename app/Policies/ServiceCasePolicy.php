<?php

namespace App\Policies;

use App\Models\ServiceCase;
use App\Models\User;

/** Students see only their own cases; staff with cases.manage see all; advisors see the cases assigned to them. */
class ServiceCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && ($user->hasPermission('cases.manage') || $user->hasPermission('cases.assigned'));
    }

    public function view(User $user, ServiceCase $case): bool
    {
        if (! $user->isActive()) {
            return false;
        }
        if ($case->user_id === $user->id) {
            return true;
        }

        return $user->isStaff() && ($user->hasPermission('cases.manage') || ($user->hasPermission('cases.assigned') && $case->advisor_id === $user->id));
    }

    public function create(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission('cases.manage');
    }

    public function update(User $user, ServiceCase $case): bool
    {
        return $user->isStaff() && $case->user_id !== $user->id && $this->view($user, $case);
    }

    public function delete(User $user, ServiceCase $case): bool
    {
        return $user->isStaff() && $user->hasPermission('cases.manage');
    }
}
