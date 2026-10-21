<?php

namespace App\Domain\Communication;

use App\Domain\Scholarship\DeadlineService;
use App\Enums\DeadlineType;
use App\Enums\PlanStatus;
use App\Models\ApplicationPlan;
use App\Models\CycleDeadline;
use App\Models\NotificationEvent;
use App\Models\SavedScholarship;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * NOT-01 / NOT-03 / DEAD-03: deadline-versioned, deduplicated reminder events.
 * Everything here is idempotent: running it twice produces the same rows.
 */
class ReminderScheduler
{
    public function __construct(private DeadlineService $deadlines) {}

    /** Plan statuses for which reminders still make sense. */
    private const ACTIVE_PLAN = [PlanStatus::Interested, PlanStatus::Preparing, PlanStatus::ReadyForReview];

    public function syncCycle(ScholarshipCycle $cycle): void
    {
        $userIds = SavedScholarship::where('cycle_id', $cycle->id)->pluck('user_id')
            ->merge(ApplicationPlan::where('cycle_id', $cycle->id)->whereIn('status', array_map(fn ($s) => $s->value, self::ACTIVE_PLAN))->pluck('user_id'))
            ->unique();

        // Also cancel events for users who no longer track the cycle.
        NotificationEvent::where('cycle_id', $cycle->id)->where('status', 'pending')
            ->whereNotIn('user_id', $userIds)
            ->update(['status' => 'cancelled', 'reason' => 'no_longer_tracked']);

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->syncUserCycle($user, $cycle);
        }
    }

    public function syncUserCycle(User $user, ScholarshipCycle $cycle): void
    {
        $cycle->unsetRelation('currentDeadlines');
        $deadline = $this->reminderDeadline($cycle);

        // Stale (superseded or no longer reminder-eligible) pending events are cancelled first.
        NotificationEvent::where('user_id', $user->id)->where('cycle_id', $cycle->id)->where('status', 'pending')
            ->when($deadline, fn ($q) => $q->where(fn ($w) => $w->where('deadline_id', '!=', $deadline->id)->orWhereNull('deadline_id')))
            ->update(['status' => 'cancelled', 'reason' => $deadline ? 'deadline_changed' : 'no_confirmed_deadline']);

        if (! $deadline || ! $cycle->isPublished()) {
            return;
        }

        $now = CarbonImmutable::now('UTC');
        foreach (config('sas.deadlines.reminder_days') as $days) {
            $sendAt = $this->sendAt($deadline, (int) $days);
            if ($sendAt === null || $sendAt->lte($now)) {
                continue; // never schedule events in the past
            }
            NotificationEvent::firstOrCreate(
                ['dedup_key' => sprintf('deadline:%d:%d:%d:v%d:%d', $user->id, $cycle->id, $deadline->id, $deadline->version, $days)],
                [
                    'user_id' => $user->id, 'type' => 'deadline_reminder', 'cycle_id' => $cycle->id, 'deadline_id' => $deadline->id,
                    'deadline_version' => $deadline->version, 'days_before' => $days, 'send_at' => $sendAt, 'status' => 'pending',
                ],
            );
        }
    }

    /** Only confirmed exact or date-only deadlines yield reminders; rolling / unconfirmed never do (NOT-01). */
    public function reminderDeadline(ScholarshipCycle $cycle): ?CycleDeadline
    {
        $now = CarbonImmutable::now('UTC');

        return $this->deadlines->current($cycle)
            ->filter(fn (CycleDeadline $d) => in_array($d->date_type, [DeadlineType::Exact, DeadlineType::DateOnly], true))
            ->filter(fn (CycleDeadline $d) => ($i = $this->deadlines->closeInstant($d)) !== null && $i->gt($now))
            ->sortBy(fn (CycleDeadline $d) => $this->deadlines->closeInstant($d)->getTimestamp())
            ->first();
    }

    public function sendAt(CycleDeadline $d, int $daysBefore): ?CarbonImmutable
    {
        return match ($d->date_type) {
            DeadlineType::Exact => $d->closes_at_utc ? CarbonImmutable::instance($d->closes_at_utc)->utc()->subDays($daysBefore) : null,
            // Calendar-date wording: sent at 09:00 UTC on the day N days before the published date.
            DeadlineType::DateOnly => $d->closes_on ? CarbonImmutable::parse($d->closes_on->toDateString().' 09:00:00', 'UTC')->subDays($daysBefore) : null,
            default => null,
        };
    }
}
