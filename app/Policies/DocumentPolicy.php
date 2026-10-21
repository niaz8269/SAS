<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/** AUTH-03: owner-only in R1. Staff case access is an R2 feature behind explicit associations. */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->isActive() && $document->user_id === $user->id;
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }
}
