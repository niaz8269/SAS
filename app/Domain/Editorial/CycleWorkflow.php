<?php

namespace App\Domain\Editorial;

use App\Domain\Communication\ReminderScheduler;
use App\Domain\Scholarship\DeadlineService;
use App\Domain\Scholarship\SearchIndexer;
use App\Enums\PublicationState as S;
use App\Models\CycleReview;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DATA-02 / DATA-03: Draft → In review → Changes requested | Approved → Published → Archived.
 * Editors cannot approve their own changes; an administrator override requires a recorded reason.
 */
class CycleWorkflow
{
    public function __construct(private DeadlineService $deadlines, private ReminderScheduler $reminders) {}

    public function submitForReview(ScholarshipCycle $cycle, User $actor): ScholarshipCycle
    {
        $this->guard($actor, 'content.draft');
        $this->expect($cycle, [S::Draft, S::ChangesRequested]);
        if (! $cycle->evidence()->exists()) {
            throw ValidationException::withMessages(['evidence' => 'Add at least one source with its URL, publisher and checked date before submitting.']);
        }

        return $this->move($cycle, S::InReview, $actor, 'Submitted for review', ['submitted_at' => now()]);
    }

    public function requestChanges(ScholarshipCycle $cycle, User $reviewer, string $notes): ScholarshipCycle
    {
        $this->guard($reviewer, 'content.review');
        $this->expect($cycle, [S::InReview]);
        if (trim($notes) === '') {
            throw ValidationException::withMessages(['notes' => 'Explain what needs to change.']);
        }

        return $this->move($cycle, S::ChangesRequested, $reviewer, $notes);
    }

    public function approve(ScholarshipCycle $cycle, User $reviewer, ?string $notes = null, ?string $overrideReason = null): ScholarshipCycle
    {
        $this->expect($cycle, [S::InReview]);
        $override = false;

        if ($this->isOwnChange($cycle, $reviewer)) {
            // Only an administrator override with a recorded reason may approve one's own change.
            if (! $reviewer->hasPermission('content.override') || blank($overrideReason)) {
                throw new AuthorizationException('You cannot approve your own changes.');
            }
            $override = true;
            $notes = trim('Override: '.$overrideReason.' '.$notes);
        } else {
            $this->guard($reviewer, 'content.review');
        }

        // DATA-02: an aggregator alone is not verification evidence.
        if (! $cycle->evidence()->where('publisher_type', '!=', 'aggregator')->exists()) {
            throw ValidationException::withMessages(['evidence' => 'An official source (funder, government, embassy or university) is required. Aggregators are leads only.']);
        }

        return $this->move($cycle, S::Approved, $reviewer, $notes, ['approved_by' => $reviewer->id, 'approved_at' => now()], $override);
    }

    public function publish(ScholarshipCycle $cycle, User $publisher): ScholarshipCycle
    {
        $this->guard($publisher, 'content.publish');
        $this->expect($cycle, [S::Approved]);

        return DB::transaction(function () use ($cycle, $publisher) {
            $evidence = $cycle->evidence()->where('publisher_type', '!=', 'aggregator')->first();
            $cycle = $this->move($cycle, S::Published, $publisher, 'Published', [
                'published_at' => now(), 'last_verified_at' => $evidence->checked_at, 'verified_by' => $cycle->approved_by,
                'evidence_id' => $evidence->id, 'archived_at' => null,
            ]);
            $this->scheduleReview($cycle);
            app(SearchIndexer::class)->reindex($cycle);
            $this->deadlines->refresh($cycle);
            $this->reminders->syncCycle($cycle->fresh(['programme']));

            return $cycle;
        });
    }

    public function archive(ScholarshipCycle $cycle, User $actor, ?string $reason = null): ScholarshipCycle
    {
        $this->guard($actor, 'content.publish');
        $this->expect($cycle, [S::Published, S::Approved, S::Draft, S::ChangesRequested]);

        $cycle = $this->move($cycle, S::Archived, $actor, $reason ?? 'Archived', ['archived_at' => now()]);
        $this->reminders->syncCycle($cycle->fresh(['programme'])); // cancels pending reminders (cycle no longer published)

        return $cycle;
    }

    /** DATA-04: 30-day cadence, 7 days when closing within 30 days. */
    public function scheduleReview(ScholarshipCycle $cycle): void
    {
        $days = $this->deadlines->isClosingSoon($cycle, 30)
            ? config('sas.review.cadence_days_closing_soon') : config('sas.review.cadence_days');
        $cycle->update(['review_due_at' => now()->addDays($days)]);
    }

    private function isOwnChange(ScholarshipCycle $cycle, User $user): bool
    {
        if ($cycle->created_by === $user->id) {
            return true;
        }

        return CycleReview::where('cycle_id', $cycle->id)->where('to_state', S::InReview->value)->where('reviewer_id', $user->id)->exists();
    }

    private function guard(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new AuthorizationException("Missing permission {$permission}.");
        }
    }

    /** @param list<S> $allowed */
    private function expect(ScholarshipCycle $cycle, array $allowed): void
    {
        if (! in_array($cycle->publication_state, $allowed, true)) {
            throw ValidationException::withMessages(['state' => "A cycle in state '{$cycle->publication_state->label()}' cannot make this transition."]);
        }
    }

    private function move(ScholarshipCycle $cycle, S $to, User $actor, ?string $notes, array $extra = [], bool $override = false): ScholarshipCycle
    {
        return DB::transaction(function () use ($cycle, $to, $actor, $notes, $extra, $override) {
            $from = $cycle->publication_state;
            $cycle->forceFill(['publication_state' => $to] + $extra)->save();
            CycleReview::create(['cycle_id' => $cycle->id, 'reviewer_id' => $actor->id, 'from_state' => $from->value,
                'to_state' => $to->value, 'notes' => $notes, 'override' => $override]);
            AuditLog::record('cycle.'.$to->value, $cycle, ['state' => $from->value], ['state' => $to->value], $notes, $actor);

            return $cycle;
        });
    }
}
