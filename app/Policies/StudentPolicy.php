<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/** Counsellors see and update the students assigned to them; student managers see every student and assign counsellors. */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && ($user->hasPermission('students.manage') || $user->hasPermission('students.assigned'));
    }

    public function view(User $user, Student $student): bool
    {
        return $this->viewAny($user) && ($user->hasPermission('students.manage') || $user->counsels($student));
    }

    /** Record a status, a comment or a message. */
    public function update(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }

    public function assign(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission('students.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Student $student): bool
    {
        return false;
    }
}
