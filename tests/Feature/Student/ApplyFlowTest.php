<?php

namespace Tests\Feature\Student;

use App\Domain\Cases\CaseService;
use App\Domain\Documents\Requirements;
use App\Domain\Scholarship\ChangeDeadline;
use App\Domain\StudentWorkspace\PlanService;
use App\Models\DegreeLevel;
use App\Models\DocumentKind;
use App\Models\DocumentRule;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ApplyFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents']);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function scholarship(string $title, array $levelCodes = ['master']): ScholarshipCycle
    {
        $cycle = ScholarshipCycle::factory()->published()->create(['programme_id' => ScholarshipProgramme::factory()->create(['title' => $title])->id]);
        $cycle->degreeLevels()->sync(DegreeLevel::whereIn('code', $levelCodes)->pluck('id'));

        return $cycle;
    }

    private function rule(ScholarshipCycle $cycle, string $key, string $requirement, ?string $levelCode = null, ?string $note = null): void
    {
        DocumentRule::create(['kind_id' => DocumentKind::where('key', $key)->value('id'), 'programme_id' => $cycle->programme_id, 'degree_level_id' => $levelCode ? DegreeLevel::where('code', $levelCode)->value('id') : null, 'requirement' => $requirement, 'note' => $note]);
    }

    private function upload(User $student, string $type): void
    {
        $this->actingAs($student)->post('/student/documents', ['file' => UploadedFile::fake()->createWithContent('f.pdf', self::PDF), 'type' => $type, 'title' => $type, 'acknowledge' => '1'])->assertRedirect();
    }

    public function test_a_new_student_lands_on_the_application_form_after_verifying_their_email(): void
    {
        $student = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $student->id, 'hash' => sha1($student->email)]);

        $this->actingAs($student)->get($url)->assertRedirect(route('student.form.show'));
        $this->get(route('student.form.show'))->assertOk()->assertSee('Application form')->assertSee('only do this once');
    }

    public function test_the_student_chooses_scholarships_and_sees_the_documents_each_one_needs(): void
    {
        $chevening = $this->scholarship('Chevening Test Award');
        $csc = $this->scholarship('CSC Test Award', ['bachelor', 'master']);
        $this->rule($chevening, 'recommendation', 'required', null, 'Two reference letters from your referees.');
        $this->rule($chevening, 'pre_admission_letter', 'required');
        $this->rule($csc, 'csca', 'required', 'bachelor', 'Bring your CSCA score report.');
        $this->rule($csc, 'csca', 'not_needed', 'master');
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('student.choose.index'))->assertOk()->assertSee('Chevening Test Award')->assertSee('CSC Test Award')->assertSee('Apply for this');
        $this->get(route('student.choose.index', ['q' => 'chevening']))->assertSee('Chevening Test Award')->assertDontSee('CSC Test Award');
        $this->get(route('student.choose.index', ['level' => 'bachelor']))->assertSee('CSC Test Award')->assertDontSee('Chevening Test Award');

        $this->post(route('student.applications.store'), ['cycle_id' => $chevening->id])->assertRedirect();
        $plan = $student->applicationPlans()->firstOrFail();
        $this->get(route('student.applications.show', $plan))->assertOk()
            ->assertSee('Documents needed for this scholarship')->assertSee('Two reference letters from your referees.')->assertSee('Pre-admission letter');
        $this->get(route('student.choose.index'))->assertSee('Chosen: see documents');
    }

    public function test_documents_the_scholarship_does_not_ask_for_are_tucked_into_an_other_section(): void
    {
        $cycle = $this->scholarship('Focused Award');
        $this->rule($cycle, 'pre_admission_letter', 'required');
        $this->rule($cycle, 'passport', 'optional', null, 'Not listed in the official requirements.');
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $cycle);

        $this->actingAs($student)->get(route('student.applications.show', $plan))->assertOk()
            ->assertSeeInOrder(['Pre-admission letter', 'Other documents you can add', 'Passport (home page)', 'CSCA score report']);
    }

    public function test_each_scholarship_shows_its_own_list_and_the_degree_level_changes_it(): void
    {
        $csc = $this->scholarship('CSC Test Award', ['bachelor', 'master']);
        $this->rule($csc, 'csca', 'required', 'bachelor', 'Bring your CSCA score report.');
        $this->rule($csc, 'csca', 'not_needed');
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $csc);

        $this->actingAs($student)->get(route('student.applications.show', $plan))->assertDontSee('CSCA score report');
        $this->put(route('student.applications.level.update', $plan), ['degree_level_id' => DegreeLevel::where('code', 'bachelor')->value('id')])->assertRedirect();
        $this->get(route('student.applications.show', $plan))->assertSee('CSCA score report')->assertSee('Mandatory');
        $this->put(route('student.applications.level.update', $plan), ['degree_level_id' => DegreeLevel::where('code', 'master')->value('id')]);
        $this->get(route('student.applications.show', $plan))->assertDontSee('CSCA score report');
    }

    public function test_a_scholarship_with_one_degree_level_needs_no_choice(): void
    {
        $only = $this->scholarship('Master Only Award', ['master']);
        $this->rule($only, 'recommendation', 'required', 'master');
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $only);

        $rows = app(Requirements::class)->forScholarship($plan->cycle, $plan->degree_level_id, $student->id)->keyBy(fn ($r) => $r['kind']->key);
        $this->assertSame('required', $rows['recommendation']['requirement']);
    }

    public function test_a_document_uploaded_once_counts_for_every_scholarship_that_asks_for_it(): void
    {
        $a = $this->scholarship('Award A');
        $b = $this->scholarship('Award B');
        $this->rule($a, 'passport', 'required');
        $this->rule($b, 'passport', 'required');
        $this->rule($b, 'cv', 'required');
        $student = User::factory()->create();
        $planA = app(PlanService::class)->create($student, $a);
        $planB = app(PlanService::class)->create($student, $b);

        $this->upload($student, 'passport');
        $progress = fn ($plan) => app(Requirements::class)->progress(app(Requirements::class)->forScholarship($plan->cycle, null, $student->id));
        $this->assertNotContains('Passport (home page)', $progress($planA)['missing']);
        $this->assertNotContains('Passport (home page)', $progress($planB)['missing']);
        $this->assertContains('CV', $progress($planB)['missing'], 'a document only Award B asks for is still missing there');
        $this->assertNotContains('CV', $progress($planA)['missing']);
    }

    public function test_the_checklist_upload_works_from_a_plan_page_and_the_dashboard_tracks_progress(): void
    {
        $a = $this->scholarship('Award A');
        $this->rule($a, 'passport', 'required');
        foreach (['photo', 'degree_certificate', 'transcript'] as $k) {
            $this->rule($a, $k, 'optional');
        }
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $a);

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()
            ->assertSee('Getting started')->assertSee('Fill in your application form')->assertSee('1 chosen')->assertSee('1 mandatory documents still missing')->assertSee('0 of 1 mandatory documents');

        $this->upload($student, 'passport');
        $this->get(route('student.dashboard'))->assertSee('All mandatory documents provided')->assertSee('1 of 1 mandatory documents');
        $this->get(route('student.applications.show', $plan))->assertSee('All 1 mandatory documents are provided.')->assertSee('% complete');
    }

    public function test_a_student_cannot_change_the_level_of_someone_elses_application(): void
    {
        $a = $this->scholarship('Award A');
        $owner = User::factory()->create();
        $plan = app(PlanService::class)->create($owner, $a);

        $this->actingAs(User::factory()->create())->put(route('student.applications.level.update', $plan), ['degree_level_id' => 1])->assertForbidden();
        $this->assertNull($plan->refresh()->degree_level_id);
    }

    public function test_a_case_opened_for_a_chosen_scholarship_keeps_the_students_degree_level(): void
    {
        $csc = $this->scholarship('CSC Test Award', ['bachelor', 'master']);
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $csc);
        $plan->update(['degree_level_id' => DegreeLevel::where('code', 'master')->value('id')]);

        $case = app(CaseService::class)->open($student, $csc, null, []);
        $this->assertSame($plan->degree_level_id, $case->degree_level_id);
    }

    public function test_closed_calls_are_hidden_until_asked_for(): void
    {
        $this->travelTo('2027-01-01 00:00:00');
        $closed = $this->scholarship('Closed Award');
        app(ChangeDeadline::class)($closed, ['date_type' => 'exact', 'local_closes_at' => '2026-12-01 17:00', 'source_timezone' => 'UTC']);
        $this->scholarship('Open Award');
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('student.choose.index'))->assertSee('Open Award')->assertDontSee('Closed Award');
        $this->get(route('student.choose.index', ['show_closed' => 1]))->assertSee('Closed Award');
    }
}
