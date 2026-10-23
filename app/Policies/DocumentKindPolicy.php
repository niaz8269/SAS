<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\DocumentKind;
use App\Models\User;

/** Which documents exist and which are mandatory is a service-desk decision (documents.manage). */
class DocumentKindPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission('documents.manage');
    }

    public function view(User $user, DocumentKind $kind): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, DocumentKind $kind): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, DocumentKind $kind): bool
    {
        return $this->viewAny($user) && ! in_array($kind->key, ['photo', 'passport', 'degree_certificate', 'transcript', 'other'], true) && ! Document::where('type', $kind->key)->exists();
    }
}
