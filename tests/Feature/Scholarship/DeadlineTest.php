<?php

namespace Tests\Feature\Scholarship;

use App\Domain\Communication\Preferences;
use App\Domain\Communication\ReminderScheduler;
use App\Domain\Scholarship\ChangeDeadline;
use App\Domain\Scholarship\DeadlineService;
use App\Enums\ApplicationStatus;
use App\Models\NotificationEvent;
use App\Models\SavedScholarship;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeadlineTest extends TestCase
{
    use RefreshDatabase;

    private function cycle(): ScholarshipCycle
    {
        return ScholarshipCycle::factory()->published()->create();
    }

    public function test_exact_deadline_in_utc_plus_8_closes_at_the_right_instant_for_a_utc_minus_5_viewer(): void
    {
        $this->travelTo('2027-01-01 00:00:00');
        $cycle = $this->cycle();
        $deadline = app(ChangeDeadline::class)($cycle, [
            'date_type' => 'exact', 'local_closes_at' => '2027-03-31 17:00', 'source_timezone' => 'Asia/Shanghai',
        ]);

        $this->assertEquals('2027-03-31 09:00:00', $deadline->closes_at_utc->utc()->format('Y-m-d H:i:s'));

        $view = app(DeadlineService::class)->describe($deadline, 'America/New_York');
        $this->assertStringContainsString('31 March 2027, 17:00 (Asia/Shanghai)', $view['primary']); // source time first
        $this->assertStringContainsString('31 March 2027, 05:00 your time (America/New_York)', $view['secondary']); // 09:00Z = 05:00 EDT
        $this->assertTrue($view['countdown']);

        $this->travelTo('2027-03-31 08:59:00');
        $this->assertSame(ApplicationStatus::Open, app(DeadlineService::class)->statusFor($cycle->fresh()));
        $this->travelTo('2027-03-31 09:00:01');
        $this->assertSame(ApplicationStatus::Closed, app(DeadlineService::class)->statusFor($cycle->fresh()));
    }

    public function test_date_only_deadline_never_gets_a_fabricated_closing_time_or_countdown(): void
    {
        $this->travelTo('2027-01-01 00:00:00');
        $cycle = $this->cycle();
        $d = app(ChangeDeadline::class)($cycle, ['date_type' => 'date_only', 'closes_on' => '2027-02-15', 'source_text' => '15 February']);

        $this->assertNull($d->closes_at_utc);
        $view = app(DeadlineService::class)->describe($d);
        $this->assertFalse($view['countdown']);
        $this->assertNull($view['closes_at']);
        $this->assertSame('Closing time not confirmed', $view['note']);
        $this->assertSame('15 February 2027', $view['primary']);
    }

    public function test_date_only_call_is_still_open_during_the_published_day_in_any_zone(): void
    {
        $cycle = $this->cycle();
        app(ChangeDeadline::class)($cycle, ['date_type' => 'date_only', 'closes_on' => '2027-02-15']);
        $this->travelTo('2027-02-15 20:00:00'); // evening UTC of the published day
        $this->assertSame(ApplicationStatus::Open, app(DeadlineService::class)->statusFor($cycle->fresh()));
        $this->travelTo('2027-02-16 13:00:00'); // day has ended everywhere
        $this->assertSame(ApplicationStatus::Closed, app(DeadlineService::class)->statusFor($cycle->fresh()));
    }

    public function test_rolling_and_unannounced_calls_have_no_countdown_and_are_never_closing_soon(): void
    {
        $service = app(DeadlineService::class);
        foreach (['rolling', 'not_announced', 'route_dependent'] as $type) {
            $cycle = $this->cycle();
            $d = app(ChangeDeadline::class)($cycle, ['date_type' => $type]);
            $this->assertFalse($service->describe($d)['countdown']);
            $this->assertFalse($service->isClosingSoon($cycle->fresh()));
            $this->assertNull($service->nextClosing($cycle->fresh()));
        }
    }

    public function test_exact_deadline_requires_a_valid_iana_zone(): void
    {
        $this->expectException(ValidationException::class);
        app(ChangeDeadline::class)($this->cycle(), ['date_type' => 'exact', 'local_closes_at' => '2027-03-31 17:00', 'source_timezone' => 'Not/AZone']);
    }

    public function test_changing_a_deadline_creates_a_new_version_and_keeps_history(): void
    {
        $this->travelTo('2027-01-01');
        $cycle = $this->cycle();
        $action = app(ChangeDeadline::class);
        $first = $action($cycle, ['date_type' => 'exact', 'local_closes_at' => '2027-03-31 17:00', 'source_timezone' => 'UTC']);
        $second = $action($cycle, ['date_type' => 'exact', 'local_closes_at' => '2027-04-30 17:00', 'source_timezone' => 'UTC'], null, 'Extended by funder');

        $this->assertSame(2, $second->version);
        $this->assertNotNull($first->fresh()->superseded_at);
        $this->assertSame(1, $cycle->currentDeadlines()->count());
        $this->assertDatabaseHas('audits', ['action' => 'deadline.changed', 'reason' => 'Extended by funder']);
    }

    public function test_extension_cancels_stale_reminders_and_schedules_one_replacement_set(): void
    {
        $this->travelTo('2027-01-01 00:00:00');
        $cycle = $this->cycle();
        $user = User::factory()->create();
        SavedScholarship::create(['user_id' => $user->id, 'cycle_id' => $cycle->id]);
        Preferences::ensureDefaults($user);

        $action = app(ChangeDeadline::class);
        $action($cycle, ['date_type' => 'exact', 'local_closes_at' => '2027-03-31 12:00', 'source_timezone' => 'UTC']);
        $this->assertSame(4, NotificationEvent::where('status', 'pending')->count());

        $action($cycle->fresh(), ['date_type' => 'exact', 'local_closes_at' => '2027-05-31 12:00', 'source_timezone' => 'UTC'], null, 'Extension');

        $this->assertSame(4, NotificationEvent::where('status', 'cancelled')->where('reason', 'deadline_changed')->count());
        $pending = NotificationEvent::where('status', 'pending')->get();
        $this->assertCount(4, $pending);
        $this->assertSame(['2027-05-01', '2027-05-17', '2027-05-24', '2027-05-30'], $pending->map(fn ($e) => $e->send_at->format('Y-m-d'))->sort()->values()->all());

        // Rescheduling to the same data must not duplicate events (idempotent).
        app(ReminderScheduler::class)->syncCycle($cycle->fresh(['programme']));
        $this->assertSame(4, NotificationEvent::where('status', 'pending')->count());
    }

    public function test_reminders_in_the_past_are_not_scheduled_and_rolling_calls_get_none(): void
    {
        $this->travelTo('2027-03-28 00:00:00');
        $cycle = $this->cycle();
        $user = User::factory()->create();
        SavedScholarship::create(['user_id' => $user->id, 'cycle_id' => $cycle->id]);
        app(ChangeDeadline::class)($cycle, ['date_type' => 'exact', 'local_closes_at' => '2027-03-31 12:00', 'source_timezone' => 'UTC']);
        // Only the 1-day reminder (and not the 30/14/7-day ones whose time has passed) remains.
        $this->assertSame([1], NotificationEvent::pluck('days_before')->all());

        $rolling = $this->cycle();
        SavedScholarship::create(['user_id' => $user->id, 'cycle_id' => $rolling->id]);
        app(ChangeDeadline::class)($rolling, ['date_type' => 'rolling']);
        $this->assertSame(0, NotificationEvent::where('cycle_id', $rolling->id)->count());
    }

    public function test_status_is_correct_at_read_time_even_if_the_stored_value_is_stale(): void
    {
        $this->travelTo('2027-01-01');
        $cycle = $this->cycle();
        app(ChangeDeadline::class)($cycle, ['date_type' => 'exact', 'local_closes_at' => '2027-01-02 12:00', 'source_timezone' => 'UTC']);
        $this->assertSame(ApplicationStatus::Open, $cycle->fresh()->application_status);

        $this->travelTo('2027-01-03'); // no scheduled job has run yet
        $this->assertSame(ApplicationStatus::Open, $cycle->fresh()->application_status);
        $this->assertSame(ApplicationStatus::Closed, $cycle->fresh()->effectiveStatus());
        $this->assertFalse(app(DeadlineService::class)->isClosingSoon($cycle->fresh()));
    }
}