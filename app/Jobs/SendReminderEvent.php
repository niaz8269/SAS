<?php

namespace App\Jobs;

use App\Domain\Communication\Preferences;
use App\Domain\Scholarship\DeadlineService;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Notifications\DeadlineReminderNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * NOT-03: safe to run any number of times for the same event. State is rechecked before sending,
 * and each channel is delivered at most once per event (delivery ledger).
 */
class SendReminderEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $eventId)
    {
        $this->onQueue('reminders');
    }

    public function uniqueId(): string
    {
        return 'reminder-'.$this->eventId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(DeadlineService $deadlines): void
    {
        $event = NotificationEvent::with(['user', 'cycle.programme', 'deadline'])->find($this->eventId);
        if (! $event || $event->status !== 'pending') {
            return; // already sent, cancelled or skipped
        }
        if ($event->send_at->isFuture()) {
            return;
        }

        $skip = fn (string $reason) => $event->update(['status' => 'skipped', 'reason' => $reason]);
        $user = $event->user;
        $cycle = $event->cycle;
        $deadline = $event->deadline;

        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            $skip('account_inactive');

            return;
        }
        if (! $cycle || ! $cycle->isPublished()) {
            $skip('cycle_not_published');

            return;
        }
        // Recheck against the *current* deadline version.
        $current = $deadlines->current($cycle)->firstWhere('id', $event->deadline_id);
        if (! $deadline || $deadline->superseded_at !== null || ! $current || $current->version !== $event->deadline_version) {
            $event->update(['status' => 'cancelled', 'reason' => 'deadline_changed']);

            return;
        }
        $close = $deadlines->closeInstant($deadline);
        if (! $close || $close->isPast()) {
            $skip('deadline_passed');

            return;
        }

        $channels = [];
        if (Preferences::enabled($user, 'deadline_reminders', 'mail')) {
            $channels[] = 'mail';
        }
        if (Preferences::enabled($user, 'deadline_reminders', 'in_app')) {
            $channels[] = 'database';
        }
        if ($channels === []) {
            $skip('no_consent');

            return;
        }

        $alreadyDelivered = NotificationDelivery::where('event_id', $event->id)->where('status', 'sent')->pluck('channel')->all();
        $pending = array_values(array_filter($channels, fn ($c) => ! in_array($c, $alreadyDelivered, true)));

        foreach ($pending as $channel) {
            try {
                $user->notifyNow(new DeadlineReminderNotification($event, $cycle, $deadline, [$channel]));
                NotificationDelivery::create(['event_id' => $event->id, 'channel' => $channel, 'status' => 'sent', 'attempt' => $event->attempts + 1]);
            } catch (Throwable $e) {
                NotificationDelivery::create(['event_id' => $event->id, 'channel' => $channel, 'status' => 'failed', 'attempt' => $event->attempts + 1, 'error' => substr($e->getMessage(), 0, 500)]);
                $event->increment('attempts');
                throw $e; // retry with backoff; sent channels are not repeated
            }
        }

        DB::transaction(fn () => $event->update(['status' => 'sent', 'sent_at' => now(), 'attempts' => $event->attempts + 1]));
    }

    public function failed(Throwable $e): void
    {
        NotificationEvent::whereKey($this->eventId)->where('status', 'pending')->update(['status' => 'failed', 'reason' => substr($e->getMessage(), 0, 250)]);
    }
}
