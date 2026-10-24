<?php

namespace Tests\Feature\Partner;

use App\Domain\Agents\AgentMessaging;
use App\Domain\Agents\AgentStudents;
use App\Domain\Cases\CaseService;
use App\Domain\Counselling\Counselling;
use App\Domain\StudentWorkspace\PlanService;
use App\Enums\Role;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\AgentMessagesRelationManager;
use App\Models\Invoice;
use App\Models\PartnerOrganisation;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AgentWrote;
use App\Notifications\SasWroteToAgent;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

/** Apply to many scholarships, CSV exports, the monthly statement, and the conversation between an agency and SAS about a student. */
class AgentExtrasTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Filament::setCurrentPanel('admin');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function agent(string $name = 'Agency One'): User
    {
        $org = new PartnerOrganisation(['name' => $name, 'kind' => 'agent', 'contact_email' => 'agent@example.test']);
        $org->forceFill(['status' => 'active'])->save();
        $user = User::factory()->role(Role::PartnerManager)->create(['name' => $name.' person']);
        $user->forceFill(['partner_organisation_id' => $org->id])->save();

        return $user->refresh();
    }

    private function studentOf(User $agent, string $name, string $email): User
    {
        return app(AgentStudents::class)->add($agent->partnerOrganisation, $agent, ['surname' => 'Khan', 'name' => $name, 'email' => $email, 'country' => 'Pakistan', 'passport_number' => 'AB1234567', 'date_of_birth' => '2001-05-14'], invite: false);
    }

    public function test_several_scholarships_are_started_at_once_for_a_student_and_for_an_agents_student(): void
    {
        $a = $this->publishedCycle();
        $b = $this->publishedCycle();
        $c = $this->publishedCycle();
        $student = User::factory()->create();

        $this->actingAs($student)->post(route('student.applications.store-many'), ['cycle_ids' => [$a->id, $b->id]])->assertRedirect(route('student.applications.index'));
        $this->assertSame(2, $student->applicationPlans()->count());
        $this->post(route('student.applications.store-many'), ['cycle_ids' => [$a->id, $c->id]])->assertSessionHas('status');
        $this->assertSame(3, $student->applicationPlans()->count(), 'the one already started is not repeated');
        $this->post(route('student.applications.store-many'), [])->assertSessionHasErrors('cycle_ids');
        $this->get(route('student.choose.index'))->assertOk()->assertSee('Start the ticked applications');

        $agent = $this->agent();
        $mine = $this->studentOf($agent, 'Amina Khan', 'amina@example.test');
        $this->actingAs($agent)->post('/partner/students/'.$mine->public_id.'/applications/many', ['cycle_ids' => [$a->id, $b->id]])->assertRedirect();
        $this->assertSame(2, $mine->applicationPlans()->count(), 'the applications belong to the student, started by the agent');
        $other = $this->studentOf($this->agent('Agency Two'), 'Other Student', 'other@example.test');
        $this->post('/partner/students/'.$other->public_id.'/applications/many', ['cycle_ids' => [$a->id]])->assertForbidden();
    }

    public function test_the_agent_exports_only_their_own_students_and_applications_safely(): void
    {
        $agent = $this->agent();
        $mine = $this->studentOf($agent, '=Evil Name', 'mine@example.test');
        $theirs = $this->studentOf($this->agent('Agency Two'), 'Not Yours', 'theirs@example.test');
        app(PlanService::class)->create($mine, $this->publishedCycle());
        app(PlanService::class)->create($theirs, $this->publishedCycle());

        $csv = $this->actingAs($agent)->get(route('partner.export.students'))->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('content-type'));
        $body = $csv->streamedContent();
        $this->assertStringContainsString('mine@example.test', $body);
        $this->assertStringNotContainsString('theirs@example.test', $body);
        $this->assertStringContainsString("'=Evil Name", $body, 'a name that looks like a formula is neutralised');

        $apps = $this->get(route('partner.export.applications'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Evil Name', $apps);
        $this->assertStringNotContainsString('Not Yours', $apps);

        $this->actingAs(User::factory()->create())->get(route('partner.export.students'))->assertForbidden();
    }

    public function test_the_statement_lists_the_months_fees_for_the_agencys_students_only(): void
    {
        $agent = $this->agent();
        $mine = $this->studentOf($agent, 'Amina Khan', 'amina@example.test');
        $theirs = $this->studentOf($this->agent('Agency Two'), 'Not Yours', 'theirs@example.test');
        app(CaseService::class)->open($mine, null, null, ['title' => 'Amina case']);
        app(CaseService::class)->open($theirs, null, null, ['title' => 'Other case']);

        $page = $this->actingAs($agent)->get(route('partner.statement'))->assertOk()->assertSee('Fees statement')->assertSee('Amina case')->assertDontSee('Other case')->assertSee(now()->format('F Y'));
        $this->assertNotNull($page);
        $csv = $this->get(route('partner.statement', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Amina case', $csv);
        $this->assertStringNotContainsString('Other case', $csv);
        $this->get(route('partner.statement', ['month' => 'nonsense']))->assertSessionHasErrors('month');
        $this->get(route('partner.statement', ['month' => now()->subMonths(3)->format('Y-m')]))->assertOk()->assertSee('No fees were issued or paid');
        $this->assertSame(1, Invoice::where('user_id', $mine->id)->count());
    }

    public function test_an_agent_and_sas_talk_about_a_student_and_the_student_never_sees_it(): void
    {
        Notification::fake();
        $counsellor = User::factory()->role(Role::Advisor)->create(['name' => 'Counsellor One']);
        $admin = User::factory()->role(Role::Administrator)->create(['name' => 'Admin One']);
        $agent = $this->agent();
        $second = User::factory()->role(Role::PartnerManager)->create();
        $second->forceFill(['partner_organisation_id' => $agent->partner_organisation_id])->save();
        $student = $this->studentOf($agent, 'Amina Khan', 'amina@example.test');

        // No counsellor yet: the admins are told.
        $this->actingAs($agent)->post(route('partner.student.message', $student->public_id), ['body' => 'Is the passport scan good enough?'])->assertRedirect();
        Notification::assertSentTo($admin, AgentWrote::class);
        $this->assertTrue(AgentMessaging::awaitingAnswer($student));
        $this->get(route('partner.student.show', $student->public_id))->assertSee('Is the passport scan good enough?')->assertSee('Messages with SAS');
        $this->post(route('partner.student.message', $student->public_id), ['body' => ''])->assertSessionHasErrors('body');

        // With a counsellor, the counsellor is told instead.
        app(Counselling::class)->assign(Student::find($student->id), $counsellor);
        $this->post(route('partner.student.message', $student->public_id), ['body' => 'Second question.']);
        Notification::assertSentTo($counsellor, AgentWrote::class);

        // The admin sees it in Needs attention, and answers from the student's page.
        $this->actingAs($admin)->get('/admin/needs-attention')->assertSee('Agents waiting for an answer')->assertSee('Amina Khan');
        Livewire::test(AgentMessagesRelationManager::class, ['ownerRecord' => Student::find($student->id), 'pageClass' => ViewStudent::class])->assertSee('Write to the agency');
        app(AgentMessaging::class)->fromStaff($student, $counsellor, 'Yes, it is fine.');
        Notification::assertSentTo($agent, SasWroteToAgent::class);
        Notification::assertSentTo($second, SasWroteToAgent::class);
        $this->assertFalse(AgentMessaging::awaitingAnswer($student));

        // The student cannot reach it, and another agency cannot write about this student.
        $this->actingAs($student)->get(route('student.counsellor'))->assertDontSee('passport scan good enough');
        $this->actingAs($this->agent('Agency Two'))->post(route('partner.student.message', $student->public_id), ['body' => 'Let me in'])->assertForbidden();
        $this->expectException(\InvalidArgumentException::class);
        app(AgentMessaging::class)->fromAgent($student, $this->agent('Agency Three'), 'Nope');
    }
}
