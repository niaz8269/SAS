<?php

namespace Tests\Feature\Admin;

use App\Domain\Admin\AgentReport;
use App\Domain\Admin\BusinessReport;
use App\Domain\Admin\Desk;
use App\Domain\Admin\EnquiryConversion;
use App\Domain\Cases\CaseService;
use App\Domain\Counselling\Counselling;
use App\Domain\StudentWorkspace\PlanService;
use App\Enums\EnquiryStatus;
use App\Enums\Role;
use App\Filament\Resources\Enquiries\Pages\EditEnquiry;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\ServiceCases\Pages\ListServiceCases;
use App\Filament\Resources\ServiceCases\ServiceCaseResource;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\ApplicationPlan;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\PartnerOrganisation;
use App\Models\ServiceCase;
use App\Models\User;
use App\Notifications\AdminAlertDigest;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

/** What an admin chases and runs: Needs attention, workload, enquiry to case, agent overview, reports and bulk actions. */
class AdminDeskTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function staff(Role $role, string $name = 'Staff'): User
    {
        return User::factory()->role($role)->create(['name' => $name]);
    }

    private function enquiry(array $attrs = [], ?string $email = null): Enquiry
    {
        $e = new Enquiry(['category' => 'advisory', 'name' => $attrs['name'] ?? 'Asker', 'email' => $email ?? 'asker'.random_int(1, 99999).'@example.test', 'message' => 'I would like help with a scholarship application.']);
        $e->owner_id = $attrs['owner_id'] ?? null;
        $e->save();
        isset($attrs['age_hours']) && $e->forceFill(['created_at' => now()->subHours($attrs['age_hours'])])->save();

        return $e;
    }

    public function test_needs_attention_lists_what_is_unassigned_or_late_and_counts_only_the_late(): void
    {
        $admin = $this->staff(Role::Administrator, 'Admin One');
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $fresh = $this->enquiry(['name' => 'Fresh Fiona']);
        $slow = $this->enquiry(['name' => 'Slow Sam', 'age_hours' => 30]);
        $unassignedStudent = User::factory()->create(['name' => 'Lone Lena']);
        $waiting = User::factory()->create(['name' => 'Waiting Wendy']);
        app(Counselling::class)->assign($waiting, $counsellor);
        app(Counselling::class)->message($waiting, $waiting, 'Anyone there?')->forceFill(['created_at' => now()->subHours(60)])->save();

        $cycle = $this->publishedCycle();
        $old = app(PlanService::class)->create(User::factory()->create(['name' => 'Old Review']), $cycle);
        $old->forceFill(['stage' => 'submitted', 'review_requested_at' => now()->subDays(5)])->save();
        $stuck = app(PlanService::class)->create(User::factory()->create(['name' => 'Stuck Sara']), $this->publishedCycle());
        $stuck->forceFill(['stage' => 'returned', 'reviewed_at' => now()->subDays(9), 'review_remarks' => 'Fix'])->save();

        $case = app(CaseService::class)->open(User::factory()->create(['name' => 'Unpaid Uma']), null, null, ['title' => 'Unpaid case']);
        $charge = new Invoice(['kind' => 'agency_charge', 'description' => 'Agency charge', 'amount_cents' => 50000, 'currency' => 'USD']);
        $charge->forceFill(['case_id' => $case->id, 'user_id' => $case->user_id, 'status' => 'issued', 'issued_at' => now()->subDays(10)])->save();

        $groups = app(Desk::class)->groups();
        $names = fn (string $g) => $groups[$g]['rows']->map(fn ($r) => $r->name ?? $r->title ?? $r->application_no)->all();
        $this->assertContains('Slow Sam', $names('slow_enquiries'));
        $this->assertNotContains('Fresh Fiona', $names('slow_enquiries'));
        $this->assertContains('Fresh Fiona', $names('unassigned_enquiries'));
        $this->assertContains('Waiting Wendy', $names('unanswered'));
        $this->assertContains('Lone Lena', $names('unassigned_students'));
        $this->assertNotContains('Waiting Wendy', $names('unassigned_students'));
        $this->assertContains($old->application_no, $names('review'));
        $this->assertContains($stuck->application_no, $names('returned'));
        $this->assertContains('Unpaid case', $names('unassigned_cases'));
        $this->assertSame(1, $groups['unpaid']['rows']->count());
        $this->assertTrue(app(Desk::class)->lateCount($groups) >= 5, 'slow enquiry, waiting student, stuck return, unpaid charge and the old review are late');

        $this->actingAs($admin)->get('/admin/needs-attention')->assertOk()->assertSee('Slow Sam')->assertSee('Lone Lena')->assertSee('Unpaid case');
        $this->get('/admin')->assertOk()->assertSee('Open Needs attention');
        $this->actingAs($counsellor)->get('/admin/needs-attention')->assertForbidden();
        $this->get('/admin')->assertOk()->assertDontSee('Open Needs attention');
    }

    public function test_the_admin_alert_email_goes_to_admins_only_when_something_is_late(): void
    {
        Notification::fake();
        $admin = $this->staff(Role::Administrator, 'Admin One');
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $this->artisan('sas:admin-alerts')->assertSuccessful();
        Notification::assertNothingSent();

        $this->enquiry(['age_hours' => 40]);
        $this->artisan('sas:admin-alerts')->assertSuccessful();
        Notification::assertSentTo($admin, AdminAlertDigest::class);
        Notification::assertNotSentTo($counsellor, AdminAlertDigest::class);
    }

    public function test_an_enquiry_becomes_a_case_for_the_person_who_wrote_it(): void
    {
        $admin = $this->staff(Role::Administrator, 'Admin One');
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $student = User::factory()->create(['name' => 'Amina Khan', 'email' => 'amina@example.test']);
        $enquiry = $this->enquiry(['name' => 'Amina Khan', 'owner_id' => $counsellor->id], 'amina@example.test');
        $stranger = $this->enquiry(['name' => 'No Account'], 'nobody@example.test');
        $cycle = $this->publishedCycle();

        $this->actingAs($admin);
        Livewire::test(EditEnquiry::class, ['record' => $enquiry->getRouteKey()])->callAction('convert', ['cycle_id' => $cycle->id, 'advisor_id' => $counsellor->id, 'note' => 'Wants China'])->assertHasNoActionErrors();

        $enquiry->refresh();
        $case = ServiceCase::firstOrFail();
        $this->assertSame(EnquiryStatus::Converted, $enquiry->status);
        $this->assertSame($case->id, $enquiry->converted_case_id);
        $this->assertSame($student->id, $case->user_id);
        $this->assertSame($counsellor->id, $case->advisor_id);
        $this->assertSame($counsellor->id, $student->fresh()->counsellor_id, 'the student gets the same counsellor');
        $this->assertStringContainsString($enquiry->reference, $case->internal_note);
        $this->assertTrue(Invoice::where('case_id', $case->id)->where('kind', 'application_fee')->exists());
        $this->assertDatabaseHas('audits', ['action' => 'enquiry.converted', 'actor_id' => $admin->id]);

        $this->expectException(\InvalidArgumentException::class);
        app(EnquiryConversion::class)->convert($stranger, null, null, null, $admin); // nobody with that email has an account
    }

    public function test_work_goes_to_the_least_busy_person_and_bulk_actions_hand_it_out(): void
    {
        $admin = $this->staff(Role::Administrator, 'Admin One');
        $busy = $this->staff(Role::Advisor, 'Busy Bee');
        $free = $this->staff(Role::Advisor, 'Free Fay');
        foreach (range(1, 3) as $i) {
            app(Counselling::class)->assign(User::factory()->create(['name' => 'Busy student '.$i]), $busy);
        }
        $this->assertContains(app(Desk::class)->leastBusy()->id, [$free->id, $admin->id], 'one of the two people with nothing yet, never the busy one');
        $load = app(Desk::class)->workload()->keyBy(fn ($r) => $r['counsellor']->id);
        $this->assertSame(3, $load[$busy->id]['students']);
        $this->assertSame(0, $load[$free->id]['score']);

        $a = User::factory()->create(['name' => 'New A']);
        $this->actingAs($admin);
        Livewire::test(ListStudents::class)->callTableBulkAction('bulkAssignLeast', [$a])->assertHasNoTableBulkActionErrors();
        $this->assertNotNull($a->fresh()->counsellor_id);
        $this->assertNotSame($busy->id, $a->fresh()->counsellor_id);

        $case = app(CaseService::class)->open($a, null, null, ['title' => 'Loose case']);
        Livewire::test(ListServiceCases::class)->callTableBulkAction('assignTo', [$case], ['advisor_id' => $busy->id]);
        $this->assertSame($busy->id, $case->fresh()->advisor_id);
        Livewire::test(ListServiceCases::class)->callTableBulkAction('assignLeastBusy', [$case]);
        $this->assertNotSame($busy->id, $case->fresh()->advisor_id);

        $e1 = $this->enquiry();
        $e2 = $this->enquiry();
        Livewire::test(ListEnquiries::class)->callTableBulkAction('assignLeastBusy', [$e1, $e2]);
        $this->assertNotNull($e1->fresh()->owner_id);
        $this->assertSame(EnquiryStatus::Assigned, $e1->fresh()->status);
        Livewire::test(ListEnquiries::class)->callTableBulkAction('close', [$e1]);
        $this->assertSame(EnquiryStatus::Closed, $e1->fresh()->status);

        $this->actingAs($busy);
        Livewire::test(ListEnquiries::class)->assertTableBulkActionHidden('close');
        $this->get('/admin/workload')->assertForbidden();
    }

    public function test_agent_overview_and_reports_show_the_numbers(): void
    {
        $admin = $this->staff(Role::Administrator, 'Admin One');
        $org = PartnerOrganisation::create(['name' => 'Star Agency', 'kind' => 'agent', 'contact_email' => 'a@example.test']);
        $student = User::factory()->create(['name' => 'Agent Student']);
        $student->forceFill(['managed_by_organisation_id' => $org->id])->save();
        $plan = app(PlanService::class)->create($student, $this->publishedCycle());
        $plan->forceFill(['stage' => 'submitted', 'review_requested_at' => now()->subDays(4)])->save();
        app(CaseService::class)->open($student, null, null, ['title' => 'Agent case']);

        $row = app(AgentReport::class)->rows()->firstWhere('org.id', $org->id);
        $this->assertSame(1, $row['students']);
        $this->assertSame(1, $row['submitted']);
        $this->assertSame(4, $row['oldest_review_days']);
        $this->assertSame(1, $row['owed_count']);

        $sections = app(BusinessReport::class)->sections(now()->subDays(7), now());
        $this->assertSame(BusinessReport::SECTIONS, array_keys($sections));
        $this->assertContains('Star Agency', array_column($sections['agents']['rows'], 0));
        $this->assertSame('Opened in total', $sections['cases']['rows'][count(ServiceCase::STATUSES)][0]);

        $this->actingAs($admin)->get('/admin/agent-overview')->assertOk()->assertSee('Star Agency');
        $this->get('/admin/reports')->assertOk()->assertSee('Applications by scholarship');
        $csv = $this->get(route('admin.reports.csv', 'agents').'?from='.now()->subDays(7)->toDateString().'&to='.now()->toDateString())->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('content-type'));
        $this->assertStringContainsString('Star Agency', $csv->streamedContent());
        $this->get(route('admin.reports.csv', 'nonsense'))->assertNotFound();
        $this->actingAs($this->staff(Role::Advisor, 'Counsellor One'))->get(route('admin.reports.csv', 'agents'))->assertForbidden();
        $this->get('/admin/reports')->assertForbidden();
        $this->get('/admin/agent-overview')->assertForbidden();
    }

    public function test_a_csv_cell_that_looks_like_a_formula_is_neutralised(): void
    {
        $admin = $this->staff(Role::Administrator, 'Admin One');
        PartnerOrganisation::create(['name' => '=HYPERLINK("http://evil.test")', 'kind' => 'agent', 'contact_email' => 'a@example.test']);
        $out = $this->actingAs($admin)->get(route('admin.reports.csv', 'agents'))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $out);
        $this->assertStringNotContainsString("\n=HYPERLINK", $out);
    }

    public function test_the_case_page_shows_the_counsellors_notes_on_the_student(): void
    {
        $admin = $this->staff(Role::Administrator, 'Admin One');
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $student = User::factory()->create(['name' => 'Amina Khan']);
        app(Counselling::class)->assign($student, $counsellor);
        app(Counselling::class)->followUp($student, $counsellor, 'documents', 'A master in Germany', 'Waiting for the passport');
        $case = app(CaseService::class)->open($student, null, $counsellor, ['title' => 'Her case']);

        $this->actingAs($admin)->get(ServiceCaseResource::getUrl('edit', ['record' => $case]))->assertOk()->assertSee('A master in Germany')->assertSee('Waiting for the passport')->assertSee('Counsellor One');
        $this->assertInstanceOf(ApplicationPlan::class, new ApplicationPlan);
    }
}
