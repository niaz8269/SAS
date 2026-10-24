<?php

namespace App\Domain\Identity;

use App\Domain\Documents\DocumentService;
use App\Domain\Editorial\AuditLog;
use App\Models\ApplicationForm;
use App\Models\Audit;
use App\Models\AvailabilitySlot;
use App\Models\Consultation;
use App\Models\Enquiry;
use App\Models\ServiceCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * PRIV-02 / PRIV-04. Data export and verified erasure.
 * Erasure removes the account and everything it owns, anonymises enquiry records (kept for service history), and writes a
 * tombstone holding only a keyed hash of the email so that a restored backup can be erased again ({@see replay()}).
 */
class AccountPrivacy
{
    public function __construct(private DocumentService $documents) {}

    /** @return array<string,mixed> everything held about the user, excluding file contents and secrets */
    public function export(User $user): array
    {
        $user->load(['studentProfile', 'savedScholarships.cycle.programme', 'applicationPlans.tasks', 'applicationPlans.journeyTasks', 'applicationPlans.cycle.programme',
            'documents.versions', 'documents.comments.author', 'enquiries.messages', 'consultations', 'counsellor', 'goals']);

        $data = [
            'exported_at' => now()->toIso8601String(),
            'account' => $user->only(['name', 'email', 'phone', 'phone_country_code', 'timezone', 'created_at', 'email_verified_at']),
            'profile' => $user->studentProfile?->toArray(),
            'application_form' => ApplicationForm::where('user_id', $user->id)->first()?->data,
            'saved_scholarships' => $user->savedScholarships->map(fn ($s) => ['scholarship' => $s->cycle->label(), 'saved_at' => $s->created_at])->all(),
            'application_plans' => $user->applicationPlans->map(fn ($p) => [
                'scholarship' => $p->cycle->label(), 'status' => $p->status->value, 'status_source' => $p->status_source,
                'submitted_at' => $p->submitted_at, 'submission_reference' => $p->submission_reference, 'notes' => $p->notes,
                'tasks' => $p->tasks->map(fn ($t) => $t->only(['title', 'due_at', 'completed_at', 'notes']))->all(),
                'journey_stage' => $p->journey_stage,
                'journey_tasks' => $p->journeyTasks->map(fn ($t) => $t->only(['stage', 'title', 'due_on', 'done_at']))->all(),
            ])->all(),
            'documents' => $user->documents->map(fn ($d) => [
                'title' => $d->title, 'type' => $d->type,
                'comments' => $d->comments->map(fn ($c) => ['from' => $c->author?->name ?? 'SAS team', 'body' => $c->body, 'at' => $c->created_at])->all(),
                'versions' => $d->versions->map(fn ($v) => $v->only(['version', 'original_name', 'size', 'mime', 'checksum', 'scan_status', 'created_at']))->all(),
            ])->all(),
            'notification_preferences' => DB::table('notification_preferences')->where('user_id', $user->id)
                ->get(['category', 'channel', 'enabled', 'consent_text', 'consented_at', 'source'])->all(),
            'notifications' => $user->notifications()->get()->map(fn ($n) => ['data' => $n->data, 'created_at' => $n->created_at, 'read_at' => $n->read_at])->all(),
            'enquiries' => Enquiry::where('user_id', $user->id)->orWhere('email', $user->email)->with('publicMessages')->get()->map(fn ($e) => [
                'reference' => $e->reference, 'category' => $e->category, 'message' => $e->message, 'status' => $e->status->value, 'created_at' => $e->created_at,
                'replies' => $e->publicMessages->map(fn ($m) => ['body' => $m->body, 'at' => $m->created_at])->all(), // internal notes are not part of the export
            ])->all(),
            'consultations' => Consultation::where('user_id', $user->id)->orWhere('email', $user->email)->get()
                ->map(fn ($c) => $c->only(['reference', 'status', 'preferred_from', 'preferred_to', 'student_timezone', 'confirmed_start_utc']))->all(),
            // Counselling. The counsellor's private follow-up notes (status, what the student wants, comment, call log) and agent messages are staff records and are not part of the export.
            'counsellor' => $user->counsellor?->name,
            'goals' => $user->goals?->only(['countries', 'degree', 'subject', 'budget', 'intake']),
            'counsellor_messages' => $user->counsellorMessages()->with('author:id,name')->oldest('id')->get()
                ->map(fn ($m) => ['from' => $m->author_id === $user->id ? 'you' : ($m->author?->name ?? 'SAS team'), 'body' => $m->body, 'at' => $m->created_at])->all(),
            'booked_calls' => AvailabilitySlot::where('booked_by', $user->id)->with('counsellor:id,name')->oldest('starts_at')->get()
                ->map(fn ($s) => ['counsellor' => $s->counsellor?->name, 'starts_at' => $s->starts_at, 'minutes' => $s->duration_minutes])->all(),
            'cases' => ServiceCase::where('user_id', $user->id)->with(['invoices', 'advisor:id,name', 'cycle.programme'])->get()->map(fn ($c) => [
                'title' => $c->title, 'status' => $c->statusLabel(), 'opened_at' => $c->opened_at, 'counsellor' => $c->advisor?->name,
                'invoices' => $c->invoices->map(fn ($i) => $i->only(['kind', 'description', 'amount_cents', 'currency', 'status', 'due_on', 'paid_at', 'payment_reference', 'refund_cents', 'refund_status']))->all(),
            ])->all(), // staff-only case notes and internal case messages are not included
        ];

        AuditLog::record('account.exported', $user, null, null, null, $user);

        return $data;
    }

    /** Reasons erasure cannot proceed through self-service. Staff records carry audit duties and are handled by an administrator. */
    public function blockedReason(User $user): ?string
    {
        if ($user->roleKeys() !== []) {
            return 'Staff and partner accounts are removed by an administrator.';
        }

        return null;
    }

    public function emailHash(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('app.key'));
    }

    /** @return int number of files removed */
    public function erase(User $user): int
    {
        if ($reason = $this->blockedReason($user)) {
            throw new \DomainException($reason);
        }
        $files = 0;
        $email = $user->email;
        $userId = $user->id;

        DB::transaction(function () use ($user, $email, $userId, &$files) {
            foreach ($user->documents()->with('versions')->get() as $document) {
                $files += $document->versions->count();
                $this->documents->delete($document); // files, versions and links
            }

            // Enquiries and consultations keep service history but lose everything identifying.
            foreach ([Enquiry::class, Consultation::class] as $model) {
                $model::where('user_id', $userId)->orWhere('email', $email)->get()->each(function ($row) use ($model) {
                    $row->forceFill(['user_id' => null, 'name' => 'Deleted user', 'email' => 'erased-'.$row->id.'@invalid.example', 'phone' => null])->save();
                    if ($model === Enquiry::class) {
                        $row->forceFill(['message' => '[removed at the user\'s request]', 'view_token_hash' => null, 'view_token_expires_at' => null])->save();
                        $row->messages()->delete();
                    } else {
                        $row->forceFill(['notes' => null])->save();
                    }
                });
            }

            DB::table('sessions')->where('user_id', $userId)->delete();
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            DB::table('notifications')->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $userId)->delete();

            // Audits keep the event but lose the actor link (FK is set null); remove any payload that could identify the user.
            DB::table('audits')->where('auditable_type', $user->getMorphClass())->where('auditable_id', $userId)->update(['before' => null, 'after' => null, 'ip' => null]);

            DB::table('deletion_tombstones')->updateOrInsert(['email_hash' => $this->emailHash($email)], ['erased_at' => now(), 'replayed_at' => null]);
            $user->delete(); // cascades profile, saved, plans, tasks, preferences, reminder events
            Audit::create(['actor_id' => null, 'action' => 'account.erased', 'after' => ['files_removed' => $files]]); // no actor or subject: nothing identifies the user
        });

        return $files;
    }

    /** After restoring a backup: erase again every account that was deleted since that backup (PRIV-04, UAT-16). */
    public function replay(): int
    {
        $count = 0;
        foreach (DB::table('deletion_tombstones')->get() as $tomb) {
            $user = User::all()->first(fn (User $u) => hash_equals($tomb->email_hash, $this->emailHash($u->email)));
            if ($user && ! $this->blockedReason($user)) {
                $this->erase($user);
                $count++;
            }
            // Enquiries that match the tombstone but have no account are anonymised too.
            Enquiry::all()->filter(fn ($e) => hash_equals($tomb->email_hash, $this->emailHash($e->email)))->each(
                fn ($e) => $e->forceFill(['name' => 'Deleted user', 'email' => 'erased-'.$e->id.'@invalid.example', 'phone' => null, 'message' => '[removed at the user\'s request]'])->save()
            );
            DB::table('deletion_tombstones')->where('id', $tomb->id)->update(['replayed_at' => now()]);
        }

        return $count;
    }
}
