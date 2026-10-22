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

    /**
     * May open the edit page. On a LIVE cycle anyone who can draft may open it to PROPOSE a change; the proposal is staged
     * and a second person approves it (CycleRevisions), so opening the form never changes what the public sees.
     */
    public function update(User $user, ScholarshipCycle $cycle): bool
    {
        if (! $user->hasPermission('content.draft')) {
            return false;
        }

        return match ($cycle->publication_state) {
            S::Draft, S::ChangesRequested, S::Published => true,
            S::InReview, S::Approved => $user->hasPermission('content.publish') || $user->hasPermission('content.override'),
            S::Archived => false,
        };
    }

    /** May change the cycle's child records (routes, requirements, deadlines...) immediately. Live ones are reviewer-only and audited. */
    public function editDirect(User $user, ScholarshipCycle $cycle): bool
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
