<?php

namespace Tests\Feature\Admin;

use App\Domain\Agents\AgentStudents;
use App\Domain\Cases\CaseService;
use App\Domain\Counselling\Booking;
use App\Domain\Documents\DocumentService;
use App\Domain\Identity\AccountPrivacy;
use App\Enums\Role;
use App\Filament\Resources\ServiceCases\Pages\EditServiceCase;
use App\Models\Audit;
use App\Models\AvailabilitySlot;
use App\Models\Document;
use App\Models\PartnerOrganisation;
use App\Models\User;
use App\Notifications\StudentWrote;
use App\Support\Html;
use App\Support\MailText;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

/** Fixes that came out of the security review of 24 October 2026. */
class SecurityHardeningTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Filament::setCurrentPanel('admin');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function agent(): User
    {
        $org = new PartnerOrganisation(['name' => 'Agency One', 'kind' => 'agent', 'contact_email' => 'agent@example.test']);
        $org->forceFill(['status' => 'active'])->save();
        $user = User::factory()->role(Role::PartnerManager)->create();
        $user->forceFill(['partner_organisation_id' => $org->id])->save();

        return $user->refresh();
    }

    private function managed(User $agent, string $email = 'amina@example.test'): User
    {
        return app(AgentStudents::class)->add($agent->partnerOrganisation, $agent, ['surname' => 'Khan', 'name' => 'Amina', 'email' => $email, 'country' => 'Pakistan', 'passport_number' => 'AB1234567', 'date_of_birth' => '2001-05-14'], invite: false);
    }

    private function data(string $email): array
    {
        return ['surname' => 'Khan', 'name' => 'Amina', 'email' => $email, 'country' => 'Pakistan', 'passport_number' => 'AB1234567', 'date_of_birth' => '2001-05-14'];
    }

    public function test_an_agent_cannot_change_the_email_of_or_invite_a_student_who_took_over_the_account(): void
    {
        $agent = $this->agent();
        $student = $this->managed($agent);
        $student->forceFill(['email_verified_at' => now()])->save(); // the student chose their own password

        $this->actingAs($agent)->put(route('partner.student.update', $student->public_id), $this->data('attacker@evil.test'))->assertSessionHasErrors('email');
        $this->assertSame('amina@example.test', $student->refresh()->email);

        Notification::fake();
        $this->post(route('partner.student.invite', $student->public_id))->assertSessionHas('error');
        Notification::assertNothingSent();

        // Before the student takes over, the agent can still correct the address and invite.
        $fresh = $this->managed($agent, 'new@example.test');
        $this->put(route('partner.student.update', $fresh->public_id), $this->data('corrected@example.test'))->assertSessionHasNoErrors();
        $this->assertSame('corrected@example.test', $fresh->refresh()->email);
        $this->post(route('partner.student.invite', $fresh->public_id))->assertSessionHas('status');
    }

    public function test_an_agency_has_a_daily_limit_on_invitations(): void
    {
        config(['sas.agents.invites_per_day' => 2]);
        $agent = $this->agent();
        Notification::fake();
        $service = app(AgentStudents::class);

        foreach (['a@example.test', 'b@example.test'] as $email) {
            $service->add($agent->partnerOrganisation, $agent, $this->data($email));
        }
        $this->expectException(\InvalidArgumentException::class);
        $service->add($agent->partnerOrganisation, $agent, $this->data('c@example.test'));
    }

    public function test_a_title_cannot_break_out_of_the_structured_data_block(): void
    {
        $cycle = $this->publishedCycle();
        $cycle->programme->update(['title' => 'Nice</script><script>alert(1)</script>']);

        $html = $this->get(route('scholarships.show', [$cycle->programme->slug, $cycle->slug]))->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
        $data = json_decode($m[1] ?? '', true);
        $this->assertNotNull($data, 'the structured data block is still valid JSON');
        $this->assertSame('https://schema.org', $data['@context'] ?? null);
        $this->assertStringContainsString('script', $data['itemListElement'][2]['name'], 'the title is kept, only made harmless');
    }

    public function test_a_link_that_a_browser_reads_as_another_site_is_not_kept(): void
    {
        $this->assertStringNotContainsString('href', Html::clean('<a href="/\\evil.example">x</a>'));
        $this->assertStringNotContainsString('href', Html::clean('<a href="//evil.example">x</a>'));
        $this->assertStringContainsString('href="/scholarships"', Html::clean('<a href="/scholarships">x</a>'));
    }

    public function test_the_staff_panel_sends_the_same_security_headers_as_the_rest_of_the_site(): void
    {
        $response = $this->get('/admin/login');

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_a_student_can_only_book_their_own_counsellors_times(): void
    {
        $mine = User::factory()->role(Role::Advisor)->create();
        $other = User::factory()->role(Role::Advisor)->create();
        $student = User::factory()->create();
        $student->forceFill(['counsellor_id' => $mine->id])->save();
        $slot = fn (User $c) => (new AvailabilitySlot(['starts_at' => now()->addDays(3), 'duration_minutes' => 30]))->forceFill(['counsellor_id' => $c->id]);
        $theirs = $slot($other);
        $theirs->save();
        $own = $slot($mine);
        $own->starts_at = now()->addDays(4);
        $own->save();
        Notification::fake();

        try {
            app(Booking::class)->book($theirs, $student);
            $this->fail('another counsellor\'s time was booked');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('another counsellor', $e->getMessage());
        }
        $this->assertNotNull(app(Booking::class)->book($own, $student));
    }

    public function test_an_ordinary_reset_link_expires_after_an_hour_but_an_agent_invitation_lasts_a_week(): void
    {
        $student = User::factory()->create();
        $reset = Password::broker()->createToken($student);
        $invite = Password::broker('invites')->createToken($student);
        $this->travel(2)->hours();
        $post = fn (string $token) => $this->post('/reset-password', ['token' => $token, 'email' => $student->email, 'password' => 'A-new-password-1', 'password_confirmation' => 'A-new-password-1']);

        $post($reset)->assertSessionHasErrors('email');
        $post($invite)->assertRedirect(route('login'));
    }

    public function test_the_case_form_does_not_hand_a_counsellor_the_list_of_all_students(): void
    {
        $advisor = User::factory()->role(Role::Advisor)->create();
        $mine = User::factory()->create(['name' => 'Assigned Student', 'email' => 'assigned@example.test']);
        User::factory()->create(['name' => 'Somebody Else', 'email' => 'secret-other@example.test']);
        $case = app(CaseService::class)->open($mine, null, $advisor, ['title' => 'Mine']);
        $this->actingAs($advisor);

        $html = Livewire::test(EditServiceCase::class, ['record' => $case->getRouteKey()])->html();

        $this->assertStringContainsString('Assigned Student', $html);
        $this->assertStringNotContainsString('secret-other@example.test', $html);
    }

    public function test_text_typed_by_students_cannot_become_links_in_our_emails(): void
    {
        $safe = MailText::plain('[Verify your account](https://evil.example/x) see www.evil.example *now*');

        $this->assertStringNotContainsString('](', $safe);
        $this->assertStringNotContainsString('https://evil', $safe);
        $this->assertStringNotContainsString('www.evil', $safe);
        $student = User::factory()->create(['name' => '[Click](https://evil.example) Amina']);
        $mail = (new StudentWrote($student, 'hello https://evil.example/pay'))->toMail($student);
        $rendered = (string) $mail->render();
        $this->assertStringNotContainsString('href="https://evil.example', $rendered);
    }

    public function test_a_student_can_end_their_agencys_access(): void
    {
        $agent = $this->agent();
        $student = $this->managed($agent);
        $student->forceFill(['email_verified_at' => now(), 'password' => bcrypt('a-long-password-1')])->save();
        $this->actingAs($student)->get(route('student.privacy'))->assertOk()->assertSee('Your agency')->assertSee('Agency One');

        $this->post(route('student.privacy.agency.stop'), ['password' => 'wrong-password'])->assertSessionHasErrors('password');
        $this->assertNotNull($student->refresh()->managed_by_organisation_id);

        $this->post(route('student.privacy.agency.stop'), ['password' => 'a-long-password-1'])->assertRedirect(route('student.privacy'));
        $this->assertNull($student->refresh()->managed_by_organisation_id);
        $this->assertTrue(Audit::where('action', 'agent.access_ended_by_student')->exists());
        $this->actingAs($agent)->get(route('partner.student.show', $student->public_id))->assertForbidden();
    }

    public function test_erasing_an_account_also_blanks_addresses_and_titles_in_the_audit_trail(): void
    {
        $student = User::factory()->create();
        Audit::create(['actor_id' => $student->id, 'action' => 'document.downloaded', 'ip' => '203.0.113.9', 'after' => ['title' => 'My passport']]);
        $doc = new Document(['title' => 'My passport', 'type' => 'passport']);
        $doc->forceFill(['user_id' => $student->id])->save();
        Audit::create(['actor_id' => null, 'action' => 'document.deleted', 'auditable_type' => $doc->getMorphClass(), 'auditable_id' => $doc->id, 'ip' => '203.0.113.9', 'before' => ['title' => 'My passport']]);

        app(AccountPrivacy::class)->erase($student);

        $this->assertSame([], Audit::whereNotNull('ip')->pluck('action')->all());
        $this->assertSame([], DB::table('audits')->where('before', 'like', '%passport%')->orWhere('after', 'like', '%passport%')->get(['action', 'auditable_type', 'auditable_id', 'before', 'after'])->map(fn ($r) => json_encode($r))->all());
    }

    public function test_an_image_that_declares_a_huge_canvas_is_refused(): void
    {
        $student = User::factory()->create();
        // A valid PNG signature and header announcing 20,000 x 20,000 pixels in a file of a few dozen bytes.
        $png = "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR".pack('N', 20000).pack('N', 20000)."\x08\x02\x00\x00\x00\x00\x00\x00\x00";
        $file = UploadedFile::fake()->createWithContent('scan.png', $png);

        $this->expectException(ValidationException::class);
        app(DocumentService::class)->validate($student, $file);
    }
}
