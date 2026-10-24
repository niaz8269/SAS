<?php

namespace App\Policies;

use App\Models\ReplyTemplate;
use App\Models\User;

/** Everyone who counsels can use the shared templates and manage their own; managers manage the shared ones. */
class ReplyTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && ($user->hasPermission('students.assigned') || $user->hasPermission('students.manage'));
    }

    public function view(User $user, ReplyTemplate $template): bool
    {
        return $this->viewAny($user) && ($template->owner_id === null || $template->owner_id === $user->id || $user->hasPermission('students.manage'));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ReplyTemplate $template): bool
    {
        return $this->viewAny($user) && ($template->owner_id === $user->id || ($template->owner_id === null && $user->hasPermission('students.manage')));
    }

    public function delete(User $user, ReplyTemplate $template): bool
    {
        return $this->update($user, $template);
    }
}
