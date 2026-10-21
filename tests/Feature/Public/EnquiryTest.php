<?php

namespace Tests\Feature\Public;

use App\Mail\EnquiryReceived;
use App\Mail\StaffEnquiryAlert;
use App\Models\Consultation;
use App\Models\Enquiry;
use App\Models\EnquiryMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnquiryTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return array_merge([
            'category' => 'advisory', 'name' => 'Amina', 'email' => 'amina@example.test', 'message' => 'I would like help choosing scholarships.',
            'contact_preference' => 'email', 'consent' => '1',
        ], $over);
    }

    public function test_guest_enquiry_is_persisted_with_reference_and_mail_is_queued(): void
    {
        Mail::fake();
        config(['sas.staff_notification_email' => 'staff@example.test']);

        $this->post('/enquiries', $this->payload())->assertRedirect(route('enquiries.thanks'));
        $enquiry = Enquiry::firstOrFail();
        $this->assertMatchesRegularExpression('/^ENQ-[A-Z0-9]{8}$/', $enquiry->reference);
        $this->assertSame('new', $enquiry->status->value);
        $this->assertNull($enquiry->user_id);
        $this->assertNotNull($enquiry->consent_at);

        Mail::assertQueued(EnquiryReceived::class);
        Mail::assertQueued(StaffEnquiryAlert::class);
        $this->get(route('enquiries.thanks'))->assertOk()->assertSee($enquiry->reference);
    }

    public function test_a_mail_outage_cannot_lose_the_enquiry(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('mail down'));
        try {
            $this->withoutExceptionHandling()->post('/enquiries', $this->payload());
        } catch (\RuntimeException) {
        }
        $this->assertSame(1, Enquiry::count()); // stored before any mail attempt
    }

    public function test_validation_errors_are_shown_and_phone_is_required_when_phone_is_preferred(): void
    {
        $this->post('/enquiries', $this->payload(['contact_preference' => 'phone']))->assertSessionHasErrors('phone');
        $this->post('/enquiries', $this->payload(['message' => 'short', 'consent' => null]))->assertSessionHasErrors(['message', 'consent']);
        $this->assertSame(0, Enquiry::count());
    }

    public function test_honeypot_submissions_are_dropped_quietly(): void
    {
        Mail::fake();
        $this->post('/enquiries', $this->payload(['website' => 'http://spam.example']))->assertSessionHasErrors('website');
        $this->assertSame(0, Enquiry::count());
    }

    public function test_enquiries_are_rate_limited(): void
    {
        Mail::fake();
        foreach (range(1, 3) as $i) {
            $this->post('/enquiries', $this->payload(['email' => "u$i@example.test"]))->assertRedirect();
        }
        $this->post('/enquiries', $this->payload(['email' => 'u9@example.test']))->assertStatus(429);
    }

    public function test_repeat_enquiries_are_flagged_not_merged(): void
    {
        Mail::fake();
        $this->post('/enquiries', $this->payload());
        $this->post('/enquiries', $this->payload());
        $this->assertSame(2, Enquiry::count());
        $this->assertSame(Enquiry::orderBy('id')->first()->id, Enquiry::orderByDesc('id')->first()->duplicate_of_id);
    }

    public function test_status_cannot_be_set_by_the_visitor(): void
    {
        Mail::fake();
        $this->post('/enquiries', $this->payload(['status' => 'converted', 'owner_id' => 99, 'user_id' => 99]));
        $e = Enquiry::first();
        $this->assertSame('new', $e->status->value);
        $this->assertNull($e->owner_id);
        $this->assertNull($e->user_id);
    }

    public function test_token_link_shows_only_public_messages_and_wrong_or_expired_tokens_get_404(): void
    {
        Mail::fake();
        $link = null;
        Mail::shouldReceive('to')->andReturnSelf();
        $this->post('/enquiries', $this->payload());
        $enquiry = Enquiry::first();
        $token = 'known-token-value';
        $enquiry->update(['view_token_hash' => hash('sha256', $token), 'view_token_expires_at' => now()->addDay()]);
        EnquiryMessage::create(['enquiry_id' => $enquiry->id, 'visibility' => 'public', 'body' => 'Visible reply to student']);
        EnquiryMessage::create(['enquiry_id' => $enquiry->id, 'visibility' => 'internal', 'body' => 'SECRET INTERNAL NOTE']);

        $this->get(route('enquiries.show', ['enquiry' => $enquiry->public_id, 'token' => $token]))
            ->assertOk()->assertSee('Visible reply to student')->assertDontSee('SECRET INTERNAL NOTE');
        $this->get(route('enquiries.show', ['enquiry' => $enquiry->public_id, 'token' => 'wrong']))->assertNotFound();
        $this->get(route('enquiries.show', ['enquiry' => $enquiry->public_id]))->assertNotFound();

        $enquiry->update(['view_token_expires_at' => now()->subMinute()]);
        $this->get(route('enquiries.show', ['enquiry' => $enquiry->public_id, 'token' => $token]))->assertNotFound();
    }

    public function test_a_signed_in_student_sees_their_own_enquiry_but_not_anothers(): void
    {
        Mail::fake();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a)->post('/enquiries', $this->payload());
        $enquiry = Enquiry::first();
        $this->assertSame($a->id, $enquiry->user_id);

        $this->actingAs($a)->get(route('enquiries.show', $enquiry))->assertOk();
        $this->actingAs($b)->get(route('enquiries.show', $enquiry))->assertNotFound();
    }

    public function test_consultation_is_a_request_never_a_confirmed_booking(): void
    {
        $this->post('/consultations', [
            'name' => 'Amina', 'email' => 'a@example.test', 'preferred_from' => now()->addDays(3)->toDateString(), 'student_timezone' => 'Asia/Karachi',
            'contact_method' => 'email', 'consent' => '1', 'status' => 'confirmed', 'confirmed_start_utc' => now()->addDays(3)->toDateTimeString(),
        ])->assertRedirect(route('consultations.thanks'));

        $c = Consultation::firstOrFail();
        $this->assertSame('requested', $c->status);
        $this->assertNull($c->confirmed_start_utc);
        $this->get(route('consultations.thanks'));
    }

    public function test_consultation_requires_phone_for_phone_contact_and_a_valid_timezone(): void
    {
        $base = ['name' => 'A', 'email' => 'a@example.test', 'preferred_from' => now()->addDay()->toDateString(), 'consent' => '1'];
        $this->post('/consultations', $base + ['student_timezone' => 'Asia/Karachi', 'contact_method' => 'phone'])->assertSessionHasErrors('phone');
        $this->post('/consultations', $base + ['student_timezone' => 'Mars/Olympus', 'contact_method' => 'email'])->assertSessionHasErrors('student_timezone');
    }

    public function test_correction_report_triggers_immediate_review(): void
    {
        $cycle = \App\Models\ScholarshipCycle::factory()->published()->create();
        $this->post('/corrections', ['cycle_id' => $cycle->id, 'message' => 'The deadline on the official site is different.'])->assertRedirect('/');
        $this->assertDatabaseHas('correction_reports', ['cycle_id' => $cycle->id, 'status' => 'new']);
        $this->assertTrue($cycle->fresh()->needsRecheck() || $cycle->fresh()->review_due_at->lte(now()));
    }
}
