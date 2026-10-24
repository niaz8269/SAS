<?php

namespace App\Policies;

use App\Models\ApplicationPlan;
use App\Models\User;

/** An application belongs to its student; a recruiting agent who looks after that student may work on it too. */
class ApplicationPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission('cases.manage');
    }

    /** The student and their agent work on it; staff who handle cases may read it once it has been sent to SAS for review. */
    public function view(User $user, ApplicationPlan $plan): bool
    {
        return $this->update($user, $plan)
            || ($user->isStaff() && $user->hasPermission('cases.manage') && $plan->stage !== 'draft')
            || $user->counsels($plan->user); // a student's counsellor reads every application of theirs, drafts included
    }

    public function update(User $user, ApplicationPlan $plan): bool
    {
        return $user->isActive() && ($plan->user_id === $user->id || $user->manages($plan->user));
    }

    public function delete(User $user, ApplicationPlan $plan): bool
    {
        return $this->update($user, $plan);
    }

    /** Staff with case permission decide on an application that is waiting for review. */
    public function review(User $user, ApplicationPlan $plan): bool
    {
        return $user->isStaff() && $user->hasPermission('cases.manage') && $plan->stage === 'submitted';
    }
}
