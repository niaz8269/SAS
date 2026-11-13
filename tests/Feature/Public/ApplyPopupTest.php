<?php

namespace Tests\Feature\Public;

use App\Mail\EnquiryReceived;
use App\Mail\StaffEnquiryAlert;
use App\Models\Enquiry;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The enquiry pop-up on the home page: shown to visitors who are not signed in, and its form becomes an ordinary enquiry. */
class ApplyPopupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Cache::flush();
        $this->withoutMiddleware(ThrottleRequests::class); // several posts in one test; the limit itself belongs to the shared "enquiries" limiter
    }

    private function payload(array $over = []): array
    {
        return $over + ['degree' => 'master', 'subject' => 'engineering', 'name' => 'Aisha Khan', 'nationality' => 'pakistan', 'email' => 'aisha@example.com',
            'whatsapp' => '+92 300 1234567', 'consent' => '1', '_ts' => Crypt::encryptString((string) now()->subMinute()->timestamp)];
    }

    public function test_the_home_page_has_the_popup_for_guests_only(): void
    {
        $this->get('/')->assertOk()->assertSee('Ask SAS about studying abroad')->assertSee(route('enquiries.quick'), false)
            ->assertSee('Select your country')->assertSee('Pakistan');

        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertDontSee('Ask SAS about studying abroad');
    }

    public function test_it_can_be_switched_off_by_configuration(): void
    {
        config(['sas.apply_popup' => false]);
        $this->get('/')->assertOk()->assertDontSee('Ask SAS about studying abroad');
    }

    public function test_the_form_becomes_an_enquiry_with_a_consent_record_and_an_email(): void
    {
        Mail::fake();

        $this->postJson(route('enquiries.quick'), $this->payload())->assertOk()->assertJson(['ok' => true])->assertJsonStructure(['reference']);

        $enquiry = Enquiry::firstOrFail();
        $this->assertSame('Aisha Khan', $enquiry->name);
        $this->assertSame('aisha@example.com', $enquiry->email);
        $this->assertSame('+92 300 1234567', $enquiry->phone);
        $this->assertSame('phone', $enquiry->contact_preference);
        $this->assertNotNull($enquiry->consent_at);
        $this->assertStringContainsString('WhatsApp', $enquiry->consent_text);
        foreach (['Degree level: Master', 'Subject: Engineering', 'Nationality: Pakistan', 'WhatsApp: +92 300 1234567'] as $line) {
            $this->assertStringContainsString($line, $enquiry->message);
        }
        Mail::assertQueued(EnquiryReceived::class);

        // The pop-up's enquiries always reach the enquiries mailbox, with the visitor's details, and replying answers the visitor.
        Mail::assertQueued(StaffEnquiryAlert::class, fn (StaffEnquiryAlert $m) => $m->hasTo('enquiry@sas.cas.moe') && $m->hasReplyTo('aisha@example.com'));
        $html = (new StaffEnquiryAlert($enquiry))->render();
        foreach (['Aisha Khan', 'aisha@example.com', '+92 300 1234567', 'Nationality: Pakistan'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_whatsapp_and_the_choices_are_optional_but_name_nationality_email_and_consent_are_not(): void
    {
        Mail::fake();
        $this->postJson(route('enquiries.quick'), $this->payload(['degree' => '', 'subject' => '', 'whatsapp' => '']))->assertOk();
        $this->assertSame('email', Enquiry::firstOrFail()->contact_preference);
        $this->assertStringContainsString('Degree level: not chosen', Enquiry::firstOrFail()->message);

        $this->postJson(route('enquiries.quick'), ['_ts' => '', 'consent' => ''])->assertStatus(422)->assertJsonValidationErrors(['name', 'nationality', 'email', 'consent']);
        $this->postJson(route('enquiries.quick'), $this->payload(['nationality' => 'atlantis']))->assertStatus(422)->assertJsonValidationErrors(['nationality']);
        $this->postJson(route('enquiries.quick'), $this->payload(['whatsapp' => 'call me']))->assertStatus(422)->assertJsonValidationErrors(['whatsapp']);
        $this->assertSame(1, Enquiry::count());
    }

    public function test_bots_are_refused_and_a_far_too_fast_submit_is_dropped_quietly(): void
    {
        Mail::fake();
        // A filled honeypot fails validation, exactly like the contact form.
        $this->postJson(route('enquiries.quick'), $this->payload(['website' => 'http://spam.example']))->assertStatus(422)->assertJsonValidationErrors(['website']);
        // A submit within two seconds of the page load looks automated: it is answered as if it worked, and nothing is stored or sent.
        $this->postJson(route('enquiries.quick'), $this->payload(['_ts' => Crypt::encryptString((string) now()->timestamp)]))->assertOk()->assertJson(['ok' => true, 'reference' => null]);
        $this->assertSame(0, Enquiry::count());
        Mail::assertNothingQueued();
    }
}
