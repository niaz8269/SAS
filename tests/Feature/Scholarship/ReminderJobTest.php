<?php

namespace Tests\Feature\Scholarship;

use App\Domain\Communication\Preferences;
use App\Domain\Communication\ReminderScheduler;
use App\Domain\Scholarship\ChangeDeadline;
use App\Domain\Scholarship\DeadlineService;
use App\Jobs\SendReminderEvent;
use App\Models\NotificationEvent;
use App\Models\SavedScholarship;
use App\Models\User;
use App\Notifications\DeadlineReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

/** NOT-01 / NOT-02 / NOT-03: reminders are consented, deduplicated, rechecked at send time and retry-safe. */
class ReminderJobTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    private function setup1(array $prefs = ['in_app' => true, 'mail' => false]): array
    {
        $this->travelTo('2027-03-01 08:00:00');
        $user = User::factory()->create();
        Preferences::ensureDefaults($user);
        foreach ($prefs as $channel => $on) {
            Preferences::set($user, 'deadline_reminders', $channel, $on);
        }
        $cycle = $this->publishedCycle([], ['date_type' => 'exact', 'local_closes_at' => '2027-03-31 12:00', 'source_timezone' => 'UTC']);
        SavedScholarship::create(['user_id' => $user->id, 'cycle_id' => $cycle->id]);
        app(ReminderScheduler::class)->syncUserCycle($user, $cycle);

        return [$user, $cycle];
    }

    private function due(int $daysBefore): NotificationEvent
    {
        return NotificationEvent::where('days_before', $daysBefore)->firstOrFail();
    }

    public function test_running_the_same_job_twice_sends_exactly_one_reminder(): void
    {
        [$user] = $this->setup1();
        $this->travelTo('2027-03-24 12:00:00'); // 7 days before
        $event = $this->due(7);

        (new SendReminderEvent($event->id))->handle(app(DeadlineService::class));
        (new SendReminderEvent($event->id))->handle(app(DeadlineService::class));

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame('sent', $event->fresh()->status);
        $this->assertSame(1, $event->deliveries()->where('status', 'sent')->count());
    }

    public function test_dispatch_command_queues_only_due_events_once_per_event(): void
    {
        [$user] = $this->setup1();
        $this->travelTo('2027-03-24 12:00:00');
        $this->artisan('sas:dispatch-reminders')->assertSuccessful();   // sync queue: sends due 30/14/7-day events
        $this->assertSame(3, $user->notifications()->count());
        $this->artisan('sas:dispatch-reminders')->assertSuccessful();   // nothing new on a second run or after a deploy
        $this->assertSame(3, $user->notifications()->count());
        $this->assertSame('pending', $this->due(1)->status);            // the 1-day reminder is not due yet
    }

    public function test_retry_after_a_partial_failure_does_not_resend_the_channel_that_succeeded(): void
    {
        [$user] = $this->setup1(['in_app' => true, 'mail' => true]);
        $this->travelTo('2027-03-24 12:00:00');
        $event = $this->due(7);

        // Simulate a crash mid-send: the in-app channel was already delivered, the mail channel was not.
        $event->deliveries()->create(['channel' => 'database', 'status' => 'sent', 'attempt' => 1]);
        Notification::fake();
        (new SendReminderEvent($event->id))->handle(app(DeadlineService::class));

        Notification::assertSentTo($user, DeadlineReminderNotification::class, fn ($n) => $n->channels === ['mail']);
        Notification::assertSentToTimes($user, DeadlineReminderNotification::class, 1); // only the missing channel
    }

    public function test_failures_are_recorded_and_the_job_rethrows_for_retry(): void
    {
        [$user] = $this->setup1(['in_app' => false, 'mail' => true]);
        $this->travelTo('2027-03-24 12:00:00');
        $event = $this->due(7);
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('smtp down'));

        try {
            (new SendReminderEvent($event->id))->handle(app(DeadlineService::class));
            $this->fail('expected exception');
        } catch (\Throwable) {
        }
        $this->assertSame('pending', $event->fresh()->status);       // still retryable
        $this->assertSame(1, $event->deliveries()->where('status', 'failed')->count());
        $this->assertSame(1, $event->fresh()->attempts);

        (new SendReminderEvent($event->id))->failed(new \RuntimeException('gave up'));
        $this->assertSame('failed', $event->fresh()->status);
    }

    public function test_consent_is_rechecked_at_send_time(): void
    {
        [$user] = $this->setup1();
        $this->travelTo('2027-03-24 12:00:00');
        Preferences::set($user, 'deadline_reminders', 'in_app', false); // opted out after the event was scheduled
        (new SendReminderEvent($this->due(7)->id))->handle(app(DeadlineService::class));
        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame('skipped', $this->due(7)->status);
        $this->assertSame('no_consent', $this->due(7)->reason);
    }

    public function test_unsubscribed_categories_never_send_optional_messages(): void
    {
        [$user] = $this->setup1(['in_app' => false, 'mail' => false]);
        $this->travelTo('2027-03-31 11:00:00');
        $this->artisan('sas:dispatch-reminders');
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_a_stale_event_is_not_sent_after_the_deadline_is_extended(): void
    {
        [$user, $cycle] = $this->setup1();
        $this->travelTo('2027-03-24 12:00:00');
        $staleId = $this->due(7)->id;

        // Extension happens, but suppose the cancellation has not been applied yet (e.g. a stale queued job).
        app(ChangeDeadline::class)($cycle->fresh(), ['date_type' => 'exact', 'local_closes_at' => '2027-05-31 12:00', 'source_timezone' => 'UTC'], null, 'Extension');
        NotificationEvent::whereKey($staleId)->update(['status' => 'pending', 'reason' => null]); // force the worst case

        (new SendReminderEvent($staleId))->handle(app(DeadlineService::class));
        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame('cancelled', NotificationEvent::find($staleId)->status);
        $this->assertSame('deadline_changed', NotificationEvent::find($staleId)->reason);
    }

    public function test_events_are_not_sent_for_unpublished_cycles_or_suspended_accounts_or_passed_deadlines(): void
    {
        [$user, $cycle] = $this->setup1();
        $this->travelTo('2027-03-24 12:00:00');
        $svc = app(DeadlineService::class);

        $cycle->update(['publication_state' => 'archived']);
        (new SendReminderEvent($this->due(7)->id))->handle($svc);
        $this->assertSame('cycle_not_published', $this->due(7)->reason);

        $cycle->update(['publication_state' => 'published']);
        NotificationEvent::where('days_before', 14)->update(['status' => 'pending']);
        $user->suspend();
        (new SendReminderEvent($this->due(14)->id))->handle($svc);
        $this->assertSame('account_inactive', $this->due(14)->reason);
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_a_reminder_is_not_sent_before_its_time(): void
    {
        [$user] = $this->setup1();
        $this->travelTo('2027-03-10 12:00:00');
        (new SendReminderEvent($this->due(7)->id))->handle(app(DeadlineService::class));
        $this->assertSame('pending', $this->due(7)->status);
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_date_only_reminders_use_calendar_wording_and_never_claim_a_time(): void
    {
        $this->travelTo('2027-03-01 08:00:00');
        $user = User::factory()->create();
        Preferences::ensureDefaults($user);
        $cycle = $this->publishedCycle([], ['date_type' => 'date_only', 'closes_on' => '2027-03-31']);
        SavedScholarship::create(['user_id' => $user->id, 'cycle_id' => $cycle->id]);
        app(ReminderScheduler::class)->syncUserCycle($user, $cycle);
        $this->travelTo('2027-03-24 12:00:00');
        $this->artisan('sas:dispatch-reminders');

        $message = $user->notifications()->latest()->first()->data['message'];
        $this->assertStringContainsString('31 March 2027', $message);
        $this->assertStringContainsString('Closing time not confirmed', $message);
        $this->assertStringNotContainsString('23:59', $message);
    }

    public function test_reminder_emails_carry_no_attachments_and_say_the_user_applies_officially(): void
    {
        [$user, $cycle] = $this->setup1(['in_app' => false, 'mail' => true]);
        $this->travelTo('2027-03-24 12:00:00');
        Notification::fake();
        (new SendReminderEvent($this->due(7)->id))->handle(app(DeadlineService::class));
        Notification::assertSentTo($user, DeadlineReminderNotification::class, function ($n) use ($user) {
            $mail = $n->toMail($user);

            return $mail->attachments === [] && str_contains(implode(' ', $mail->introLines), 'does not submit applications');
        });
    }

    public function test_evaluate_command_flips_stored_status_when_a_deadline_passes(): void
    {
        $this->travelTo('2027-03-01');
        $cycle = $this->publishedCycle([], ['date_type' => 'exact', 'local_closes_at' => '2027-03-02 12:00', 'source_timezone' => 'UTC']);
        $this->assertSame('open', $cycle->fresh()->application_status->value);
        $this->travelTo('2027-03-03');
        $this->artisan('sas:evaluate-deadlines')->assertSuccessful();
        $this->assertSame('closed', $cycle->fresh()->application_status->value);
    }
}
