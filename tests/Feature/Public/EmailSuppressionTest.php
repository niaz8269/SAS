<?php

namespace Tests\Feature\Public;

use App\Domain\Communication\EmailSuppressions;
use App\Domain\Communication\Preferences;
use App\Domain\Communication\ReminderScheduler;
use App\Domain\Scholarship\DeadlineService;
use App\Enums\Role;
use App\Jobs\SendReminderEvent;
use App\Mail\EnquiryReceived;
use App\Models\EmailSuppression;
use App\Models\Enquiry;
use App\Models\NotificationEvent;
use App\Models\SavedScholarship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class EmailSuppressionTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    private function signed(array $events, ?string $secret = 'topsecret', ?string $sig = null)
    {
        config(['sas.mail_webhook_secret' => $secret]);
        $body = json_encode(['events' => $events]);
        $sig ??= hash_hmac('sha256', $body, 'topsecret');

        return $this->call('POST', '/webhooks/email/acme', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $sig], $body);
    }

    public function test_the_endpoint_does_not_exist_without_a_configured_secret(): void
    {
        $this->signed([], '')->assertNotFound();
    }

    public function test_unsigned_or_wrongly_signed_requests_are_refused_and_change_nothing(): void
    {
        $evt = [['id' => 'e1', 'type' => 'hard_bounce', 'email' => 'gone@example.test']];
        $this->signed($evt, 'topsecret', 'bad')->assertUnauthorized();
        $this->signed($evt, 'topsecret', '')->assertUnauthorized();
        $this->assertSame(0, EmailSuppression::count());
    }

    public function test_hard_bounces_and_complaints_suppress_but_soft_bounces_and_deliveries_do_not(): void
    {
        $this->signed([
            ['id' => 'e1', 'type' => 'hard_bounce', 'email' => 'Gone@Example.test'],
            ['id' => 'e2', 'type' => 'complaint', 'email' => 'angry@example.test'],
            ['id' => 'e3', 'type' => 'soft_bounce', 'email' => 'full@example.test'],
            ['id' => 'e4', 'type' => 'delivered', 'email' => 'ok@example.test'],
            ['id' => '', 'type' => 'hard_bounce', 'email' => 'noid@example.test'],
            ['id' => 'e6', 'type' => 'hard_bounce', 'email' => 'not-an-email'],
        ])->assertOk()->assertJson(['processed' => 4]);

        $s = app(EmailSuppressions::class);
        $this->assertTrue($s->isSuppressed('gone@example.test'));
        $this->assertTrue($s->isSuppressed(' GONE@example.test '));
        $this->assertTrue($s->isSuppressed('angry@example.test'));
        $this->assertFalse($s->isSuppressed('full@example.test'));
        $this->assertFalse($s->isSuppressed('ok@example.test'));
        $this->assertFalse($s->isSuppressed('noid@example.test'));
    }

    public function test_replaying_the_same_events_is_harmless(): void
    {
        $evt = [['id' => 'e1', 'type' => 'hard_bounce', 'email' => 'gone@example.test']];
        $this->signed($evt)->assertJson(['processed' => 1]);
        $this->signed($evt)->assertJson(['processed' => 0]);
        $this->assertSame(1, EmailSuppression::count());
        $this->assertSame(1, DB::table('webhook_events')->count());
        $this->assertSame(1, DB::table('audits')->where('action', 'email.suppressed')->count());
    }

    public function test_the_webhook_needs_no_csrf_token_and_is_rate_limited(): void
    {
        $this->signed([])->assertOk();
        foreach (range(1, 125) as $i) {
            $last = $this->signed([]);
        }
        $this->assertSame(429, $last->getStatusCode());
    }

    public function test_no_email_is_sent_to_a_suppressed_address(): void
    {
        $this->signed([['id' => 'e1', 'type' => 'hard_bounce', 'email' => 'gone@example.test']]);
        $e = new Enquiry(['category' => 'advisory', 'name' => 'S', 'email' => 'gone@example.test', 'message' => 'A long enough message']);
        $e->save();
        $mailer = Mail::mailer('array');

        $mailer->to('gone@example.test')->send(new EnquiryReceived($e, 'https://example.test/x'));
        $this->assertCount(0, $mailer->getSymfonyTransport()->messages());

        $mailer->to('fine@example.test')->send(new EnquiryReceived($e, 'https://example.test/x'));
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
    }

    public function test_reminders_skip_the_email_channel_for_suppressed_addresses_but_still_use_in_app(): void
    {
        $this->travelTo('2027-03-01 08:00:00');
        $user = User::factory()->create(['email' => 'gone@example.test']);
        Preferences::ensureDefaults($user);
        Preferences::set($user, 'deadline_reminders', 'mail', true);
        $cycle = $this->publishedCycle([], ['date_type' => 'exact', 'local_closes_at' => '2027-03-31 12:00', 'source_timezone' => 'UTC']);
        SavedScholarship::create(['user_id' => $user->id, 'cycle_id' => $cycle->id]);
        app(ReminderScheduler::class)->syncUserCycle($user, $cycle);
        app(EmailSuppressions::class)->suppress('gone@example.test', 'bounce', 'test');

        $this->travelTo('2027-03-24 12:00:00');
        $event = NotificationEvent::where('days_before', 7)->firstOrFail();
        (new SendReminderEvent($event->id))->handle(app(DeadlineService::class));

        $this->assertSame(1, $user->notifications()->count()); // in-app still delivered
        $this->assertSame(1, $event->deliveries()->where('channel', 'mail')->where('status', 'suppressed')->count());
        $this->assertSame(0, $event->deliveries()->where('channel', 'mail')->where('status', 'sent')->count());
        $this->assertSame('sent', $event->fresh()->status);
    }

    public function test_lifting_a_block_is_audited_and_resumes_email(): void
    {
        $s = app(EmailSuppressions::class);
        $s->suppress('back@example.test', 'bounce');
        $admin = User::factory()->role(Role::Administrator)->create();
        $s->lift(EmailSuppression::firstOrFail(), $admin);
        $this->assertFalse($s->isSuppressed('back@example.test'));
        $this->assertDatabaseHas('audits', ['action' => 'email.suppression_lifted', 'actor_id' => $admin->id]);
        $s->suppress('back@example.test', 'complaint'); // blocked again on the next event
        $this->assertTrue($s->isSuppressed('back@example.test'));
        $this->assertSame(1, EmailSuppression::count());
    }
}
