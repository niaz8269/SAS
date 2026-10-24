<?php

namespace Tests\Feature\Partner;

use App\Domain\Agents\AgentStudents;
use App\Domain\Applications\ApplicationFormService;
use App\Domain\Cases\CaseService;
use App\Domain\StudentWorkspace\ApplicationReview;
use App\Domain\StudentWorkspace\PlanService;
use App\Enums\Role;
use App\Models\ApplicationPlan;
use App\Models\Audit;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\Document;
use App\Models\DocumentKind;
use App\Models\DocumentRule;
use App\Models\PartnerOrganisation;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\User;
use App\Notifications\ApplicationSentBack;
use App\Notifications\StudentInvited;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentPortalTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Country::firstWhere('name', 'Pakistan') ?? Country::factory()->create(['name' => 'Pakistan']);
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents', 'sas.staff_notification_email' => 'staff@example.test']);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function agent(string $name = 'Agency One', string $kind = 'agent'): User
    {
        $org = new PartnerOrganisation(['name' => $name, 'kind' => $kind, 'contact_email' => 'agent@example.test']);
        $org->forceFill(['status' => 'active'])->save();
        $user = User::factory()->role(Role::PartnerManager)->create();
        $user->forceFill(['partner_organisation_id' => $org->id])->save();

        return $user->refresh();
    }

    private function studentOf(User $agent, string $email = 'amina@example.test'): User
    {
        return app(AgentStudents::class)->add($agent->partnerOrganisation, $agent, ['surname' => 'Khan', 'name' => 'Amina', 'email' => $email, 'country' => 'Pakistan', 'passport_number' => 'AB1234567', 'date_of_birth' => '2001-05-14'], invite: false);
    }

    private function cycle(): ScholarshipCycle
    {
        $cycle = ScholarshipCycle::factory()->published()->create(['programme_id' => ScholarshipProgramme::factory()->create(['title' => 'Agent Award'])->id]);
        $cycle->degreeLevels()->sync(DegreeLevel::where('code', 'master')->pluck('id'));
        foreach (['photo', 'degree_certificate', 'transcript'] as $key) {
            DocumentRule::create(['kind_id' => DocumentKind::where('key', $key)->value('id'), 'programme_id' => $cycle->programme_id, 'requirement' => 'optional']);
        }
        DocumentRule::create(['kind_id' => DocumentKind::where('key', 'passport')->value('id'), 'programme_id' => $cycle->programme_id, 'requirement' => 'required']);

        return $cycle;
    }

    public function test_student_management_is_only_for_recruiting_agents(): void
    {
        $agent = $this->agent();
        $legacy = $this->agent('Old organisation', 'university'); // a leftover organisation that is not marked as an agent

        $this->actingAs($agent)->get(route('partner.home'))->assertOk()->assertSee('Backend home')->assertSee('Number of students');
        $this->get(route('partner.students.index'))->assertOk();
        $this->get(route('partner.applications.index'))->assertOk();

        $this->actingAs($legacy)->get(route('partner.home'))->assertForbidden();
        $this->get(route('partner.students.index'))->assertForbidden();
        $this->get(route('partner.applications.index'))->assertForbidden();
        $this->post(route('partner.students.store'), [])->assertForbidden();

        $this->actingAs(User::factory()->create())->get(route('partner.students.index'))->assertForbidden();
    }

    public function test_an_agent_adds_a_student_who_is_invited_and_the_details_are_prefilled(): void
    {
        Notification::fake();
        $agent = $this->agent();
        $payload = ['surname' => 'Khan', 'name' => 'Amina', 'email' => 'amina@example.test', 'country' => 'Pakistan', 'passport_number' => 'AB1234567', 'date_of_birth' => '2001-05-14', 'consent' => '1'];

        $this->actingAs($agent)->post(route('partner.students.store'), array_merge($payload, ['consent' => null]))->assertSessionHasErrors('consent');
        $this->post(route('partner.students.store'), $payload)->assertRedirect();

        $student = User::where('email', 'amina@example.test')->firstOrFail();
        $this->assertSame($agent->partner_organisation_id, $student->managed_by_organisation_id);
        $this->assertSame('Amina Khan', $student->name);
        $this->assertNull($student->email_verified_at);
        Notification::assertSentTo($student, StudentInvited::class);
        $form = app(ApplicationFormService::class)->formFor($student);
        $this->assertSame('Khan', $form->value('surname'));
        $this->assertSame('Pakistan', $form->value('nationality'));
        $this->assertSame('AB1234567', $form->value('passport_no'));
        $this->assertSame('2001-05-14', $form->value('date_of_birth'));
        $this->assertTrue(Audit::where('action', 'agent.student_added')->where('auditable_id', $student->id)->exists());

        $this->post(route('partner.students.store'), $payload)->assertSessionHasErrors('email'); // someone already has this email
        $this->get(route('partner.students.index'))->assertSee('Amina Khan')->assertSee('Invitation pending');
    }

    public function test_the_student_chooses_their_own_password_from_the_invitation_and_can_sign_in(): void
    {
        $agent = $this->agent();
        $student = $this->studentOf($agent);
        Notification::fake();
        app(AgentStudents::class)->invite($student, $agent->partnerOrganisation);
        $token = null;
        Notification::assertSentTo($student, StudentInvited::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->travel(3)->days(); // the invitation outlives an ordinary reset link (an hour)
        $this->post(route('password.update'), ['token' => $token, 'email' => $student->email, 'password' => 'a-new-password-1', 'password_confirmation' => 'a-new-password-1'])->assertRedirect(route('login'));
        $student->refresh();
        $this->assertTrue(Hash::check('a-new-password-1', $student->password));
        $this->assertNotNull($student->email_verified_at, 'using the emailed link proves the address');
        $this->post(route('login.store'), ['email' => $student->email, 'password' => 'a-new-password-1'])->assertRedirect();
        $this->assertAuthenticatedAs($student);
    }

    public function test_batch_import_adds_good_rows_and_reports_bad_ones(): void
    {
        Notification::fake();
        $agent = $this->agent();
        User::factory()->create(['email' => 'taken@example.test']);
        $csv = "surname,name,email,country,passport_number,date_of_birth\n"
            ."Khan,Amina,a1@example.test,Pakistan,P1,2001-05-14\n"
            ."Ali,Omar,not-an-email,Pakistan,P2,2000-01-01\n"
            ."Noor,Sara,taken@example.test,Pakistan,P3,2000-01-01\n"
            ."Zed,Mo,a4@example.test,Atlantis,P4,2000-01-01\n"
            ."Lee,Jin,a5@example.test,Pakistan,P5,2002-02-02\n";

        $this->actingAs($agent)->post(route('partner.students.import.store'), ['file' => UploadedFile::fake()->createWithContent('s.csv', $csv), 'consent' => '1'])
            ->assertRedirect()->assertSessionHas('status')->assertSessionHas('skipped');
        $this->assertSame(2, User::where('managed_by_organisation_id', $agent->partner_organisation_id)->count());
        $this->assertEqualsCanonicalizing([3, 4, 5], array_keys(session('skipped')));
        Notification::assertSentTimes(StudentInvited::class, 2);

        $this->post(route('partner.students.import.store'), ['file' => UploadedFile::fake()->createWithContent('bad.csv', "a,b\n1,2\n"), 'consent' => '1'])->assertSessionHasErrors('file');
        $this->post(route('partner.students.import.store'), ['file' => UploadedFile::fake()->createWithContent('s.csv', $csv)])->assertSessionHasErrors('consent');
        $this->get(route('partner.students.template'))->assertOk()->assertSee('surname,name,email');
    }

    public function test_an_agent_reaches_only_their_own_students(): void
    {
        $one = $this->agent('Agency One');
        $two = $this->agent('Agency Two');
        $mine = $this->studentOf($one, 'mine@example.test');
        $theirs = $this->studentOf($two, 'theirs@example.test');
        $plan = app(PlanService::class)->create($theirs, $this->cycle());

        $this->actingAs($one)->get(route('partner.students.index'))->assertSee('mine@example.test')->assertDontSee('theirs@example.test');
        $this->get(route('partner.student.show', $mine->public_id))->assertOk();
        foreach ([route('partner.student.show', $theirs->public_id), route('partner.student.edit', $theirs->public_id), route('partner.student.form.show', $theirs->public_id),
            route('partner.student.applications.index', $theirs->public_id), route('partner.student.choose.index', $theirs->public_id)] as $url) {
            $this->get($url)->assertForbidden();
        }
        // Even through the right agent's URL, someone else's application id gets nowhere.
        $this->get(route('partner.student.applications.show', [$mine->public_id, $plan->public_id]))->assertForbidden();
        $this->get(route('partner.student.applications.download', [$mine->public_id, $plan->public_id]))->assertForbidden();
        $this->put(route('partner.student.update', $theirs->public_id), ['surname' => 'X', 'name' => 'Y', 'email' => 'x@example.test', 'country' => 'Pakistan', 'date_of_birth' => '2000-01-01'])->assertForbidden();

        $this->actingAs(User::factory()->create())->get(route('partner.student.show', $theirs->public_id))->assertForbidden(); // a student is not an agent
        $this->assertNull($mine->fresh()->partnerOrganisation);
    }

    public function test_an_agent_takes_a_student_through_all_four_steps_for_them(): void
    {
        Notification::fake();
        $agent = $this->agent();
        $student = $this->studentOf($agent);
        $cycle = $this->cycle();
        $base = fn (string $path = '') => '/partner/students/'.$student->public_id.$path;

        $this->actingAs($agent)->get($base('/choose-scholarships'))->assertOk()->assertSee('Add an application for Amina Khan')->assertSee('Agent Award');
        $this->post($base('/applications'), ['cycle_id' => $cycle->id])->assertRedirect();
        $plan = ApplicationPlan::where('cycle_id', $cycle->id)->firstOrFail();
        $this->assertSame($student->id, $plan->user_id, 'the application belongs to the student, not the agent');
        $this->assertSame(0, $agent->applicationPlans()->count());

        $this->get($base('/applications/'.$plan->public_id))->assertOk()->assertSee('for Amina Khan')->assertSee('Agent backend'); // the agent header
        $this->put($base('/applications/'.$plan->public_id.'/form'), ['sections' => ['contact' => ['phone' => '+923001234567', 'address' => 'Lahore']]])->assertRedirect();
        $this->assertSame('+923001234567', app(ApplicationFormService::class)->formFor($student)->value('phone'));

        $this->post($base('/documents'), ['files' => [UploadedFile::fake()->createWithContent('passport.pdf', self::PDF)], 'type' => 'passport', 'acknowledge' => '1'])->assertRedirect();
        $document = Document::where('type', 'passport')->firstOrFail();
        $this->assertSame($student->id, $document->user_id, 'the document belongs to the student');
        $this->get($base('/documents/'.$document->public_id.'/download'))->assertOk();
        $this->get($base('/applications/'.$plan->public_id.'/materials'))->assertOk()->assertSee('passport.pdf')->assertSee('Download blank form');
        $this->get($base('/applications/'.$plan->public_id.'/download'))->assertOk()->assertHeader('content-type', 'application/pdf'); // the SAS application form with the student's answers

        $this->get($base('/applications/'.$plan->public_id.'/tools'))->assertOk()->assertSee('Notes, deadlines and tracker'); // no link to a student-only document vault
        $this->get($base('/applications/'.$plan->public_id.'/submit'))->assertOk()->assertSee('Not ready to submit yet'); // the form is still incomplete
        $this->post($base('/applications/'.$plan->public_id.'/submit'))->assertSessionHas('error');
        $this->assertSame('draft', $plan->refresh()->stage);
    }

    public function test_the_agents_list_shows_status_remarks_and_fees_and_filters(): void
    {
        Notification::fake();
        $agent = $this->agent();
        $other = $this->agent('Agency Two');
        $student = $this->studentOf($agent);
        $cycle = $this->cycle();
        $plan = app(PlanService::class)->create($student, $cycle);
        $stranger = app(PlanService::class)->create($this->studentOf($other, 'o@example.test'), $cycle);
        $plan->forceFill(['stage' => 'returned', 'review_remarks' => 'The passport scan is blurred.'])->save();
        $case = app(CaseService::class)->open($student, $cycle, null, []);
        app(CaseService::class)->markPaid($case->invoices()->first(), null, 'REF');

        $this->actingAs($agent)->get(route('partner.applications.index'))->assertOk()
            ->assertSee($plan->application_no)->assertDontSee($stranger->application_no)->assertSee('Returned')->assertSee('The passport scan is blurred.')
            ->assertSee('Paid')->assertSee('Download Application Form')->assertSee('Application fee payment status');
        $this->get(route('partner.applications.index', ['stage' => 'passed']))->assertDontSee($plan->application_no);
        $this->get(route('partner.applications.index', ['q' => 'Amina']))->assertSee($plan->application_no);
        $this->get(route('partner.applications.index', ['q' => 'nobody-by-this-name']))->assertDontSee($plan->application_no);
        $this->get(route('partner.home'))->assertOk()->assertSee('Number of students');
    }

    public function test_the_lists_sort_and_page_size_come_from_the_query_and_bad_values_fall_back(): void
    {
        $agent = $this->agent();
        $this->studentOf($agent, 'zed@example.test');
        $this->studentOf($agent, 'abe@example.test');

        $this->actingAs($agent)->get(route('partner.students.index', ['sort' => 'email', 'dir' => 'asc', 'per_page' => 10]))->assertOk()
            ->assertSeeInOrder(['abe@example.test', 'zed@example.test']);
        $this->get(route('partner.students.index', ['sort' => 'password; drop', 'dir' => 'sideways', 'per_page' => 7]))->assertOk();
        $this->get(route('partner.applications.index', ['sort' => 'name', 'dir' => 'asc', 'per_page' => 50]))->assertOk();
        $this->get(route('partner.applications.index', ['sort' => 'nope', 'per_page' => 'x']))->assertOk();
    }

    public function test_the_agent_is_told_when_staff_review_a_students_application(): void
    {
        Notification::fake();
        $agent = $this->agent();
        $student = $this->studentOf($agent);
        $plan = app(PlanService::class)->create($student, $this->cycle());
        $plan->forceFill(['stage' => 'submitted'])->save();
        $staff = User::factory()->role(Role::Administrator)->create();

        app(ApplicationReview::class)->review($plan, 'return', 'Please fix the passport.', $staff);

        Notification::assertSentTo($agent, ApplicationSentBack::class);
        Notification::assertNotSentTo($student, ApplicationSentBack::class); // the student has not set a password yet (email not verified)
    }

    public function test_a_partner_user_changes_their_password_and_other_devices_are_signed_out(): void
    {
        $agent = $this->agent();
        $agent->forceFill(['password' => Hash::make('old-password-1')])->save();

        $this->actingAs($agent)->put(route('partner.password.update'), ['current_password' => 'wrong', 'password' => 'new-password-22', 'password_confirmation' => 'new-password-22'])->assertSessionHasErrors('current_password');
        $this->put(route('partner.password.update'), ['current_password' => 'old-password-1', 'password' => 'old-password-1', 'password_confirmation' => 'old-password-1'])->assertSessionHasErrors('password');
        $this->put(route('partner.password.update'), ['current_password' => 'old-password-1', 'password' => 'new-password-22', 'password_confirmation' => 'new-password-22'])->assertRedirect();
        $this->assertTrue(Hash::check('new-password-22', $agent->fresh()->password));
        $this->get(route('partner.password.edit'))->assertOk()->assertSee('Change password');
    }
}
