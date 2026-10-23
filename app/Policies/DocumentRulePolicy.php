<?php

namespace App\Policies;

use App\Models\DocumentRule;
use App\Models\User;

class DocumentRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission('documents.manage');
    }

    public function view(User $user, DocumentRule $rule): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, DocumentRule $rule): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, DocumentRule $rule): bool
    {
        return $this->viewAny($user);
    }
}
