<?php

namespace App\Policies;

use App\Models\CaseDocumentShare;
use App\Models\Document;
use App\Models\User;

/**
 * AUTH-03: a document belongs to its owner. The only other way in is a share the student made on a case
 * (with consent recorded): staff who may view that case can then download the file, and only while the share is active.
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->isActive() && $document->user_id === $user->id;
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document) || $this->sharedWithStaff($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    private function sharedWithStaff(User $user, Document $document): bool
    {
        if (! $user->isStaff()) {
            return false;
        }

        return CaseDocumentShare::where('document_id', $document->id)->whereNull('revoked_at')
            ->whereHas('serviceCase', function ($q) use ($user, $document) {
                $q->where('user_id', $document->user_id); // the case must belong to the document's owner
                if (! $user->hasPermission('cases.manage')) {
                    $user->hasPermission('cases.assigned') ? $q->where('advisor_id', $user->id) : $q->whereRaw('1 = 0');
                }
            })->exists();
    }
}
