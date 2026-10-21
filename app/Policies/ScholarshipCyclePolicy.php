<?php

namespace App\Policies;

use App\Enums\PublicationState as S;
use App\Models\ScholarshipCycle;
use App\Models\User;

class ScholarshipCyclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && ($user->hasPermission('content.draft') || $user->hasPermission('content.review'));
    }

    public function view(User $user, ScholarshipCycle $cycle): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('content.draft');
    }

    /** Editors edit drafts; only people who can publish may edit live content (changes to live content are not staged in R1). */
    public function update(User $user, ScholarshipCycle $cycle): bool
    {
        if (! $user->hasPermission('content.draft')) {
            return false;
        }

        return match ($cycle->publication_state) {
            S::Draft, S::ChangesRequested => true,
            S::InReview, S::Approved, S::Published => $user->hasPermission('content.publish') || $user->hasPermission('content.override'),
            S::Archived => false,
        };
    }

    public function delete(User $user, ScholarshipCycle $cycle): bool
    {
        return false; // restrict deletion: archive instead (SCHEMA-06)
    }
}
