<?php

namespace Tests\Feature\Partners;

use App\Domain\Agents\PartnerApplications;
use App\Enums\Role;
use App\Filament\Resources\PartnerApplications\Pages\ManagePartnerApplications;
use App\Filament\Resources\PartnerApplications\PartnerApplicationResource;
use App\Models\Country;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Notifications\PartnerApplicationAlert;
use App\Notifications\PartnerApplicationReceived;
use App\Notifications\PartnerApplicationRejected;
use App\Notifications\PartnerApproved;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/** An agency applies on the website; only a staff approval creates the partner (agent) account. */
class PartnerApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Country::firstOrCreate(['name' => 'Pakistan'], ['iso2' => 'PK', 'slug' => 'pakistan']);
        RateLimiter::clear('partner-hour:127.0.0.1');
        RateLimiter::clear('partner-day:127.0.0.1');
    }

    private function payload(array $over = []): array
    {
        return $over + [
            'agency_name' => 'Bright Path Consultants', 'agency_country' => 'Pakistan', 'agency_phone' => '+92 300 1234567',
            'applicant_name' => 'Ayesha Khan', 'applicant_position' => 'Director', 'applicant_email' => 'Ayesha@Example.org',
            'applicant_phone' => '+92 300 7654321', 'consent' => '1',
        ];
    }

    private function staff(Role $role): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    private function pending(array $over = []): PartnerApplication
    {
        $this->post(route('partners.store'), $this->payload($over))->assertRedirect(route('partners.thanks'));

        return PartnerApplication::latest('id')->firstOrFail();
    }

    public function test_the_form_asks_for_agency_and_personal_information(): void
    {
        $this->get(route('partners.apply'))->assertOk()
            ->assertSee('Your agency')->assertSee('About you')->assertSee('name="agency_name"', false)->assertSee('name="applicant_email"', false)
            ->assertSee('name="company_site"', false);
    }

    public function test_a_valid_application_is_saved_as_waiting_and_both_sides_are_emailed(): void
    {
        Notification::fake();
        $this->post(route('partners.store'), $this->payload())->assertRedirect(route('partners.thanks'));
        $this->get(route('partners.thanks'))->assertOk()->assertSee('Thank you for applying');

        $a = PartnerApplication::sole();
        $this->assertSame('pending', $a->status);
        $this->assertSame('ayesha@example.org', $a->applicant_email);
        $this->assertTrue($a->consent_given);
        $this->assertSame(0, User::where('email', 'ayesha@example.org')->count(), 'no account before approval');
        Notification::assertSentOnDemand(PartnerApplicationReceived::class);
        Notification::assertSentOnDemand(PartnerApplicationAlert::class);
    }

    public function test_the_thank_you_page_is_only_for_people_who_just_applied(): void
    {
        $this->get(route('partners.thanks'))->assertNotFound();
    }

    public function test_required_fields_consent_country_and_website_are_checked(): void
    {
        $this->post(route('partners.store'), [])->assertSessionHasErrors(['agency_name', 'agency_country', 'agency_phone', 'applicant_name', 'applicant_position', 'applicant_email', 'applicant_phone', 'consent']);
        $this->post(route('partners.store'), $this->payload(['agency_country' => 'Atlantis', 'website' => 'javascript:alert(1)', 'years_in_business' => 'x']))
            ->assertSessionHasErrors(['agency_country', 'website', 'years_in_business']);
        $this->assertSame(0, PartnerApplication::count());
    }

    public function test_an_email_that_already_has_an_account_is_told_to_log_in(): void
    {
        User::factory()->create(['email' => 'ayesha@example.org']);
        $this->post(route('partners.store'), $this->payload())->assertSessionHasErrors('applicant_email');
        $this->assertSame(0, PartnerApplication::count());
    }

    public function test_applying_twice_is_saved_once(): void
    {
        $this->pending();
        $this->post(route('partners.store'), $this->payload(['agency_name' => 'Other']))->assertRedirect(route('partners.thanks'));
        $this->assertSame(1, PartnerApplication::count());
    }

    public function test_the_hidden_field_catches_robots_and_nothing_is_saved(): void
    {
        $this->post(route('partners.store'), $this->payload(['company_site' => 'spam.example']))->assertRedirect(route('partners.thanks'));
        $this->assertSame(0, PartnerApplication::count());
    }

    public function test_one_address_cannot_send_more_than_three_applications_an_hour(): void
    {
        foreach (range(1, 3) as $i) {
            $this->post(route('partners.store'), $this->payload(['applicant_email' => "p$i@example.org"]))->assertRedirect(route('partners.thanks'));
        }
        $this->post(route('partners.store'), $this->payload(['applicant_email' => 'p4@example.org']))->assertStatus(429);
        $this->assertSame(3, PartnerApplication::count());
    }

    public function test_a_mail_service_that_refuses_us_does_not_break_the_form(): void
    {
        config(['mail.default' => 'failing', 'mail.mailers.failing' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);
        $this->post(route('partners.store'), $this->payload())->assertRedirect(route('partners.thanks'));
        $this->assertSame(1, PartnerApplication::count());
    }

    public function test_approving_creates_the_agency_and_a_partner_account_that_can_choose_a_password_and_sign_in(): void
    {
        $application = $this->pending();
        Notification::fake();
        $admin = $this->staff(Role::Administrator);

        $result = app(PartnerApplications::class)->approve($application, $admin);

        $this->assertTrue($result['mailed']);
        $user = $result['user']->refresh();
        $this->assertSame('ayesha@example.org', $user->email);
        $this->assertTrue($user->isAgent());
        $this->assertTrue($user->hasRole(Role::PartnerManager));
        $this->assertSame($result['organisation']->id, $user->partner_organisation_id);
        $this->assertSame('Bright Path Consultants', $result['organisation']->name);
        $this->assertSame('active', $result['organisation']->status);
        $this->assertSame('approved', $application->refresh()->status);
        $this->assertSame($admin->id, $application->reviewed_by);
        Notification::assertSentTo($user, PartnerApproved::class);

        // The person chooses a password from the emailed link and then signs in to the agent portal.
        $token = Password::broker('invites')->createToken($user);
        auth()->logout();
        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])->assertSessionHasNoErrors();
        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123']);
        $this->assertAuthenticatedAs($user);
        $this->get('/partner')->assertOk();
    }

    public function test_approval_refuses_a_second_time_and_an_email_that_became_an_account(): void
    {
        $application = $this->pending();
        $admin = $this->staff(Role::Administrator);
        $service = app(PartnerApplications::class);
        $service->approve($application, $admin);
        $this->expectException(\DomainException::class);
        $service->approve($application, $admin);
    }

    public function test_approval_refuses_when_the_email_got_an_account_meanwhile(): void
    {
        $application = $this->pending();
        User::factory()->create(['email' => 'ayesha@example.org']);
        $this->expectException(\DomainException::class);
        app(PartnerApplications::class)->approve($application, $this->staff(Role::Administrator));
    }

    public function test_rejecting_keeps_the_note_creates_no_account_and_tells_the_applicant(): void
    {
        $application = $this->pending();
        Notification::fake();
        app(PartnerApplications::class)->reject($application, $this->staff(Role::Administrator), 'Please send your registration.');
        $this->assertSame('rejected', $application->refresh()->status);
        $this->assertSame('Please send your registration.', $application->review_note);
        $this->assertSame(0, User::where('email', 'ayesha@example.org')->count());
        Notification::assertSentOnDemand(PartnerApplicationRejected::class);
    }

    public function test_staff_with_the_right_can_approve_and_reject_from_the_admin_screen(): void
    {
        $one = $this->pending(['applicant_email' => 'one@example.org']);
        $two = $this->pending(['applicant_email' => 'two@example.org']);
        Notification::fake();
        $this->staff(Role::Administrator);

        Livewire::test(ManagePartnerApplications::class)->assertCanSeeTableRecords([$one, $two])
            ->callTableAction('approve', $one)->assertNotified()
            ->callTableAction('reject', $two, ['note' => 'Not now'])->assertNotified();

        $this->assertSame('approved', $one->refresh()->status);
        $this->assertSame('rejected', $two->refresh()->status);
        $this->assertTrue(User::where('email', 'one@example.org')->firstOrFail()->isAgent());
    }

    public function test_an_adviser_or_an_agent_cannot_see_or_decide_applications(): void
    {
        $this->pending();
        $this->staff(Role::Advisor);
        $this->assertFalse(PartnerApplicationResource::canViewAny());
        $this->get('/admin/partner-applications')->assertForbidden();
    }
}
