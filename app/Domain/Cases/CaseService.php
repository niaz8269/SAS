<?php

namespace App\Domain\Cases;

use App\Domain\Editorial\AuditLog;
use App\Models\Invoice;
use App\Models\PaymentOption;
use App\Models\ScholarshipCycle;
use App\Models\ServiceCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The fee rules in one place (see /legal/refunds):
 *  - opening a case issues the application fee;
 *  - a rejected application refunds part of that fee (never the whole);
 *  - an accepted application issues the agency service charge;
 *  - cancelling before work starts refunds the whole application fee; later withdrawals are not refunded.
 * Money and status only change here, and every change is audited.
 */
class CaseService
{
    /** @param array<string,mixed> $attrs title, internal_note */
    public function open(User $student, ?ScholarshipCycle $cycle, ?User $advisor, array $attrs, ?User $actor = null): ServiceCase
    {
        return DB::transaction(function () use ($student, $cycle, $advisor, $attrs, $actor) {
            $title = trim((string) ($attrs['title'] ?? '')) ?: ($cycle ? 'Application: '.$cycle->programme->title : 'Scholarship application');
            $case = new ServiceCase(['title' => $title, 'internal_note' => $attrs['internal_note'] ?? null, 'cycle_id' => $cycle?->id]);
            $case->forceFill(['user_id' => $student->id, 'advisor_id' => $advisor?->id, 'status' => 'new'])->save();
            $this->issue($case, 'application_fee', 'Application fee: '.$title, (int) config('sas.fees.application') * 100);
            AuditLog::record('case.opened', $case, null, ['student' => $student->id, 'advisor' => $advisor?->id], null, $actor);

            return $case;
        });
    }

    public function assign(ServiceCase $case, ?User $advisor, ?User $actor = null): void
    {
        $before = $case->advisor_id;
        $case->forceFill(['advisor_id' => $advisor?->id])->save();
        AuditLog::record('case.assigned', $case, ['advisor' => $before], ['advisor' => $advisor?->id], null, $actor);
    }

    public function setStatus(ServiceCase $case, string $status, ?User $actor = null): ServiceCase
    {
        if (! in_array($status, ServiceCase::NEXT[$case->status] ?? [], true)) {
            throw new InvalidArgumentException("A {$case->statusLabel()} case cannot move to ".(ServiceCase::STATUSES[$status] ?? $status).'.');
        }

        return DB::transaction(function () use ($case, $status, $actor) {
            $from = $case->status;
            $case->forceFill(['status' => $status, 'outcome_at' => in_array($status, ['accepted', 'rejected', 'withdrawn'], true) ? now() : $case->outcome_at])->save();
            $application = $case->invoices()->where('kind', 'application_fee')->first();

            if ($status === 'accepted' && ! $case->invoices()->where('kind', 'agency_charge')->where('status', '!=', 'void')->exists()) {
                $this->issue($case, 'agency_charge', 'Agency service charge: '.$case->title, (int) config('sas.fees.agency_service') * 100);
            }
            if ($status === 'rejected' && $application) {
                $this->refundOrVoid($application, (int) config('sas.fees.application_refundable') * 100);
            }
            if ($status === 'withdrawn' && $application) {
                // Whole fee back only if no work had started; otherwise nothing is refunded (cancellation policy).
                $this->refundOrVoid($application, $from === 'new' ? $application->amount_cents : 0);
            }
            AuditLog::record('case.status_changed', $case, ['status' => $from], ['status' => $status], null, $actor);

            return $case->refresh();
        });
    }

    public function markPaid(Invoice $invoice, ?PaymentOption $option, ?string $reference, ?User $actor = null): Invoice
    {
        if ($invoice->status !== 'issued') {
            throw new InvalidArgumentException('Only an invoice awaiting payment can be marked as paid.');
        }
        $invoice->forceFill(['status' => 'paid', 'paid_at' => now(), 'payment_option_id' => $option?->id, 'payment_reference' => $reference ? mb_substr(trim($reference), 0, 120) : null])->save();
        AuditLog::record('invoice.paid', $invoice, null, ['amount_cents' => $invoice->amount_cents, 'reference' => $invoice->payment_reference], null, $actor);

        return $invoice;
    }

    public function void(Invoice $invoice, ?User $actor = null): Invoice
    {
        if ($invoice->status !== 'issued') {
            throw new InvalidArgumentException('Only an invoice awaiting payment can be cancelled. A paid invoice is refunded instead.');
        }
        $invoice->forceFill(['status' => 'void'])->save();
        AuditLog::record('invoice.voided', $invoice, null, null, null, $actor);

        return $invoice;
    }

    public function markRefunded(Invoice $invoice, ?User $actor = null): Invoice
    {
        if ($invoice->refund_status !== 'due') {
            throw new InvalidArgumentException('No refund is due on this invoice.');
        }
        $invoice->forceFill(['refund_status' => 'paid', 'refunded_at' => now()])->save();
        AuditLog::record('invoice.refunded', $invoice, null, ['refund_cents' => $invoice->refund_cents], null, $actor);

        return $invoice;
    }

    private function issue(ServiceCase $case, string $kind, string $description, int $cents): Invoice
    {
        $invoice = new Invoice(['kind' => $kind, 'description' => $description, 'amount_cents' => $cents, 'currency' => 'USD']);
        $invoice->forceFill(['case_id' => $case->id, 'user_id' => $case->user_id, 'status' => 'issued'])->save();

        return $invoice;
    }

    /** A paid invoice gets a refund due (capped at what was paid); an unpaid one is simply cancelled. */
    private function refundOrVoid(Invoice $invoice, int $refundCents): void
    {
        if ($invoice->status === 'issued') {
            $invoice->forceFill(['status' => 'void'])->save();

            return;
        }
        $refundCents = min($refundCents, $invoice->amount_cents);
        if ($invoice->status === 'paid' && $refundCents > 0 && $invoice->refund_status === 'none') {
            $invoice->forceFill(['refund_cents' => $refundCents, 'refund_status' => 'due'])->save();
        }
    }
}
