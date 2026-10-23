<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/** Students see their own invoices; finance and case managers see all; advisors see invoices of cases assigned to them (read only). */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && ($user->hasPermission('finance.view') || $user->hasPermission('cases.manage') || $user->hasPermission('cases.assigned'));
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $user->isActive()) {
            return false;
        }
        if ($invoice->user_id === $user->id) {
            return true;
        }

        return $user->isStaff() && ($user->hasPermission('finance.view') || $user->hasPermission('cases.manage')
            || ($user->hasPermission('cases.assigned') && $invoice->serviceCase?->advisor_id === $user->id));
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->isStaff() && $user->hasPermission('invoices.manage');
    }

    public function create(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission('invoices.manage');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false; // invoices are voided, never deleted
    }
}
