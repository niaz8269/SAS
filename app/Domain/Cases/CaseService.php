<?php

namespace App\Domain\Cases;

use App\Domain\Editorial\AuditLog;
use App\Models\CaseDocumentShare;
use App\Models\CaseMessage;
use App\Models\Document;
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
 * Money and status only change here, every change is audited, and the student is told about it.
 */
class CaseService
{
    public function __construct(private CaseNotifier $notifier) {}

    /** @param array<string,mixed> $attrs title, internal_note */
    public function open(User $student, ?ScholarshipCycle $cycle, ?User $advisor, array $attrs, ?User $actor = null): ServiceCase
    {
        return DB::transaction(function () use ($student, $cycle, $advisor, $attrs, $actor) {
            $title = trim((string) ($attrs['title'] ?? '')) ?: ($cycle ? 'Application: '.$cycle->programme->title : 'Scholarship application');
            $case = new ServiceCase(['title' => $title, 'internal_note' => $attrs['internal_note'] ?? null, 'cycle_id' => $cycle?->id]);
            $case->forceFill(['user_id' => $student->id, 'advisor_id' => $advisor?->id, 'status' => 'new'])->save();
            $this->issue($case, 'application_fee', 'Application fee: '.$title, (int) config('sas.fees.application') * 100);
            AuditLog::record('case.opened', $case, null, ['student' => $student->id, 'advisor' => $advisor?->id], null, $actor);
            $this->notifier->tell($case, 'SAS opened a case for your application: '.$title.'. The application fee of '.config('sas.fees.currency').config('sas.fees.application').' is shown under Cases and fees, with how to pay.');

            return $case;
        });
    }

    public function assign(ServiceCase $case, ?User $advisor, ?User $actor = null): void
    {
        $before = $case->advisor_id;
        $case->forceFill(['advisor_id' => $advisor?->id])->save();
        AuditLog::record('case.assigned', $case, ['advisor' => $before], ['advisor' => $advisor?->id], null, $actor);
        if ($advisor && $advisor->id !== $before) {
            $this->notifier->tell($case, $advisor->name.' is now your counsellor for '.$case->title.'.');
        }
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

            $extra = match ($status) {
                'accepted' => ' Congratulations. The agency service charge of '.config('sas.fees.currency').config('sas.fees.agency_service').' is shown under Cases and fees.',
                'rejected' => ' We are sorry. If you had paid the application fee, '.config('sas.fees.currency').config('sas.fees.application_refundable').' of it is being refunded.',
                default => '',
            };
            $this->notifier->tell($case, 'Your case "'.$case->title.'" is now: '.$case->statusLabel().'.'.$extra);

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
        $this->notifier->tell($invoice->serviceCase, 'We recorded your payment of '.$invoice->amount().' ('.$invoice->description.'). Thank you.');

        return $invoice;
    }

    public function void(Invoice $invoice, ?User $actor = null): Invoice
    {
        if ($invoice->status !== 'issued') {
            throw new InvalidArgumentException('Only an invoice awaiting payment can be cancelled. A paid invoice is refunded instead.');
        }
        $invoice->forceFill(['status' => 'void'])->save();
        AuditLog::record('invoice.voided', $invoice, null, null, null, $actor);
        $this->notifier->tell($invoice->serviceCase, 'An invoice was cancelled: '.$invoice->description.'. You do not need to pay it.');

        return $invoice;
    }

    public function markRefunded(Invoice $invoice, ?User $actor = null): Invoice
    {
        if ($invoice->refund_status !== 'due') {
            throw new InvalidArgumentException('No refund is due on this invoice.');
        }
        $invoice->forceFill(['refund_status' => 'paid', 'refunded_at' => now()])->save();
        AuditLog::record('invoice.refunded', $invoice, null, ['refund_cents' => $invoice->refund_cents], null, $actor);
        $this->notifier->tell($invoice->serviceCase, 'Your refund of '.$invoice->refundAmount().' has been paid.');

        return $invoice;
    }

    /** The student chooses which of their documents the people handling this case may open. Anything not listed stops being shared. */
    public function syncShares(ServiceCase $case, User $student, array $documentIds, string $consentText): void
    {
        if ($case->user_id !== $student->id) {
            throw new InvalidArgumentException('This is not your case.');
        }
        $own = Document::where('user_id', $student->id)->whereIn('id', $documentIds)->pluck('id')->all();
        DB::transaction(function () use ($case, $student, $own, $consentText) {
            foreach ($own as $id) {
                $share = CaseDocumentShare::firstOrNew(['case_id' => $case->id, 'document_id' => $id]);
                if (! $share->exists || $share->revoked_at) {
                    $share->forceFill(['shared_by' => $student->id, 'consent_text' => $consentText, 'shared_at' => now(), 'revoked_at' => null])->save();
                    AuditLog::record('case.document_shared', $case, null, ['document_id' => $id], null, $student);
                }
            }
            CaseDocumentShare::where('case_id', $case->id)->whereNull('revoked_at')->whereNotIn('document_id', $own ?: [0])->get()->each(function (CaseDocumentShare $s) use ($case, $student) {
                $s->forceFill(['revoked_at' => now()])->save();
                AuditLog::record('case.document_unshared', $case, null, ['document_id' => $s->document_id], null, $student);
            });
        });
    }

    /** A message in the case. A student's message is always public; staff may reply publicly or leave an internal note. */
    public function postMessage(ServiceCase $case, User $author, string $body, string $visibility = 'public'): CaseMessage
    {
        $fromStudent = $case->user_id === $author->id;
        $visibility = $fromStudent ? 'public' : (in_array($visibility, ['public', 'internal'], true) ? $visibility : 'public');
        $message = new CaseMessage(['body' => trim($body)]);
        $message->forceFill(['case_id' => $case->id, 'author_id' => $author->id, 'visibility' => $visibility])->save();

        if ($fromStudent) {
            $case->forceFill(['awaiting_reply' => true])->save();
        } elseif ($visibility === 'public') {
            $case->forceFill(['awaiting_reply' => false])->save();
            $this->notifier->tell($case, ($author->name ?: 'Your counsellor').' replied in your case "'.$case->title.'".');
        }

        return $message;
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
