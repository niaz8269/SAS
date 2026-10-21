<?php

namespace App\Policies;

use App\Models\ApplicationPlan;
use App\Models\User;

class ApplicationPlanPolicy
{
    public function view(User $user, ApplicationPlan $plan): bool
    {
        return $user->isActive() && $plan->user_id === $user->id;
    }

    public function update(User $user, ApplicationPlan $plan): bool
    {
        return $this->view($user, $plan);
    }

    public function delete(User $user, ApplicationPlan $plan): bool
    {
        return $this->view($user, $plan);
    }
}
