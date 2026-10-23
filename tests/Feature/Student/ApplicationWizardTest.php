<?php

namespace Tests\Feature\Student;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Cases\CaseService;
use App\Domain\Documents\Requirements;
use App\Domain\StudentWorkspace\ApplicationReview;
use App\Domain\StudentWorkspace\PlanService;
use App\Enums\Role;
use App\Filament\Resources\ApplicationReviews\ApplicationReviewResource;
use App\Filament\Resources\ApplicationReviews\Pages\ListApplicationReviews;
use App\Models\ApplicationPlan;
use App\Models\DegreeLevel;
use App\Models\Document;
use App\Models\DocumentKind;
use App\Models\DocumentRule;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\User;
use App\Notifications\ApplicationSentBack;
use App\Notifications\ApplicationSubmittedAlert;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationWizardTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents', 'sas.staff_notification_email' => 'staff@example.test']);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** A scholarship that asks only for a passport, so an application can be completed quickly. */
    private function cycle(string $title = 'Wizard Award'): ScholarshipCycle
    {
        $cycle = ScholarshipCycle::factory()->published()->create(['programme_id' => ScholarshipProgramme::factory()->create(['title' => $title])->id]);
        $cycle->degreeLevels()->sync(DegreeLevel::where('code', 'master')->pluck('id'));
        foreach (['photo', 'degree_certificate', 'transcript'] as $key) {
            DocumentRule::create(['kind_id' => DocumentKind::where('key', $key)->value('id'), 'programme_id' => $cycle->programme_id, 'requirement' => 'optional']);
        }
        DocumentRule::create(['kind_id' => DocumentKind::where('key', 'passport')->value('id'), 'programme_id' => $cycle->programme_id, 'requirement' => 'required']);

        return $cycle;
    }

    private function completeForm(User $student): void
    {
        $forms = app(ApplicationFormService::class);
        $answers = [];
        foreach ($forms->sections() as $section) {
            foreach ($section['fields'] as $name => $def) {
                if ($def['required'] ?? false) {
                    $answers[$name] = match ($def[1]) {
                        'date' => '2000-01-01', 'email' => 'a@example.test', 'select' => $def['options'][0], default => 'x'
                    };
                }
            }
        }
        $forms->formFor($student)->forceFill(['data' => $answers + ['_declared_at' => now()->toDateTimeString()]])->save();
    }

    private function upload(User $student, string $type): void
    {
        $this->actingAs($student)->post('/student/documents', ['files' => [UploadedFile::fake()->createWithContent('f.pdf', self::PDF)], 'type' => $type, 'acknowledge' => '1'])->assertRedirect();
    }

    private function staff(Role $role): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['a-b']])->save();

        return $user;
    }

    public function test_the_four_steps_render_with_the_scholarship_application_number_and_step_bar(): void
    {
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $this->cycle());

        $this->assertMatchesRegularExpression('/^SAS\d{10}$/', $plan->application_no);
        $this->actingAs($student)->get(route('student.applications.show', $plan))->assertOk()
            ->assertSee('The program you are applying for is Wizard Award')->assertSee($plan->application_no)->assertSee('Apply Now')->assertSee('Apply Submit');
        $this->get(route('student.applications.form', $plan))->assertOk()->assertSee('Application Form')->assertSee('Personal information')->assertSee('name="sections[personal][surname]"', false);
        $this->get(route('student.applications.materials', $plan))->assertOk()->assertSee('Upload Materials')->assertSee('Notice')->assertSee('Currently cannot provide the document');
        $this->get(route('student.applications.submit', $plan))->assertOk()->assertSee('Apply Submit')->assertSee('Not ready to submit yet');
        $this->get(route('student.applications.preview', $plan))->assertOk()->assertSee('Application form')->assertSee($plan->application_no)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_a_reason_excuses_a_mandatory_document_and_needs_text(): void
    {
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $this->cycle());
        $passport = DocumentKind::where('key', 'passport')->value('id');

        $this->actingAs($student)->put(route('student.applications.reasons', $plan), ['cannot' => [$passport => '1'], 'reason' => [$passport => '']])->assertSessionHasErrors('reason.'.$passport);
        $this->assertSame(0, $plan->documentReasons()->count());

        $this->put(route('student.applications.reasons', $plan), ['cannot' => [$passport => '1'], 'reason' => [$passport => 'It is being renewed.']])->assertRedirect();
        $this->assertSame('It is being renewed.', $plan->documentReasons()->first()->reason);
        $this->get(route('student.applications.materials', $plan))->assertSee('Cannot provide now')->assertSee('It is being renewed.');
        $progress = app(Requirements::class)->progress(app(Requirements::class)->forPlan($plan->refresh()));
        $this->assertSame(['required' => 1, 'ready' => 1, 'excused' => 1, 'missing' => []], array_intersect_key($progress, array_flip(['required', 'ready', 'excused', 'missing'])));

        $this->put(route('student.applications.reasons', $plan), ['reason' => [$passport => 'It is being renewed.']])->assertRedirect(); // unticked
        $this->assertSame(0, $plan->documentReasons()->count());
    }

    public function test_an_incomplete_application_cannot_be_submitted_and_a_complete_one_can(): void
    {
        Notification::fake();
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $this->cycle());

        $this->actingAs($student)->post(route('student.applications.submit.store', $plan))->assertSessionHas('error');
        $this->assertSame('draft', $plan->refresh()->stage);

        $this->completeForm($student);
        $this->post(route('student.applications.submit.store', $plan))->assertSessionHas('error'); // the passport is still missing
        $this->upload($student, 'passport');
        $this->post(route('student.applications.submit.store', $plan))->assertSessionHas('status');

        $plan->refresh();
        $this->assertSame('submitted', $plan->stage);
        $this->assertNotNull($plan->review_requested_at);
        Notification::assertSentOnDemand(ApplicationSubmittedAlert::class);
        $this->get(route('student.applications.index'))->assertSee('Submitted for review');
        $this->get(route('student.applications.index', ['show' => 'finished']))->assertSee($plan->application_no);
        $this->get(route('student.applications.index', ['show' => 'unfinished']))->assertDontSee($plan->application_no);
    }

    public function test_a_submitted_application_is_locked_until_staff_return_it(): void
    {
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $this->cycle());
        $this->completeForm($student);
        $this->upload($student, 'passport');
        app(ApplicationReview::class)->submit($plan, $student);

        $this->actingAs($student)->put(route('student.applications.form.update', $plan), ['sections' => ['contact' => ['phone' => '123456']]])->assertForbidden();
        $this->put(route('student.applications.level.update', $plan), ['degree_level_id' => 1])->assertForbidden();
        $this->put(route('student.applications.reasons', $plan), [])->assertForbidden();
        $this->delete(route('student.applications.destroy', $plan))->assertForbidden();
        $this->get(route('student.applications.materials', $plan))->assertOk()->assertSee('Submitted for review')->assertDontSee('Choose files', false);

        app(ApplicationReview::class)->review($plan->refresh(), 'return', 'The passport scan is blurred.', $this->staff(Role::ServiceManager));
        $this->actingAs($student)->get(route('student.applications.materials', $plan))->assertSee('SAS returned this application')->assertSee('The passport scan is blurred.');
        $this->put(route('student.applications.form.update', $plan), ['sections' => ['contact' => ['phone' => '123456']]])->assertRedirect();
    }

    public function test_staff_return_with_remarks_or_pass_and_the_right_people_are_told(): void
    {
        Notification::fake();
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $this->cycle());
        $this->completeForm($student);
        $this->upload($student, 'passport');
        app(ApplicationReview::class)->submit($plan, $student);
        $manager = $this->staff(Role::ServiceManager);

        $this->actingAs($manager);
        $this->assertTrue(ApplicationReviewResource::canViewAny());
        Livewire::test(ListApplicationReviews::class)->assertCanSeeTableRecords([$plan])
            ->callTableAction('return', $plan, data: ['remarks' => ''])->assertHasTableActionErrors(['remarks']);
        $this->assertSame('submitted', $plan->refresh()->stage);

        Livewire::test(ListApplicationReviews::class)->callTableAction('return', $plan, data: ['remarks' => 'Please upload a clearer passport.']);
        $plan->refresh();
        $this->assertSame('returned', $plan->stage);
        $this->assertSame('Please upload a clearer passport.', $plan->review_remarks);
        $this->assertSame($manager->id, $plan->reviewed_by);
        Notification::assertSentTo($student, ApplicationSentBack::class);

        app(ApplicationReview::class)->submit($plan, $student);
        Livewire::test(ListApplicationReviews::class)->callTableAction('pass', $plan->refresh(), data: ['remarks' => 'Looks complete.']);
        $this->assertSame('passed', $plan->refresh()->stage);
        $this->assertFalse($manager->can('review', $plan), 'only an application waiting for review can be reviewed');
    }

    public function test_only_staff_who_handle_cases_see_the_review_screen_and_only_submitted_applications(): void
    {
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $this->cycle());

        foreach ([Role::Editor, Role::Finance] as $role) {
            $this->actingAs($this->staff($role));
            $this->assertFalse(ApplicationReviewResource::canViewAny(), $role->value);
            auth()->logout();
        }
        $manager = $this->staff(Role::ServiceManager);
        $this->actingAs($manager)->get(route('review.preview', $plan))->assertForbidden(); // a draft is private to the student
        $this->completeForm($student);
        $this->upload($student, 'passport');
        app(ApplicationReview::class)->submit($plan, $student);
        $this->actingAs($manager)->get(route('review.preview', $plan))->assertOk()->assertSee($plan->application_no);
        $this->assertFalse($manager->can('update', $plan), 'staff review it but do not edit the student\'s application');
    }

    public function test_staff_can_open_the_documents_of_an_application_under_review_only_then(): void
    {
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $this->cycle());
        $this->completeForm($student);
        $this->upload($student, 'passport');
        $this->upload($student, 'cv'); // not asked for by this scholarship
        $passport = Document::where('type', 'passport')->firstOrFail();
        $cv = Document::where('type', 'cv')->firstOrFail();
        $manager = $this->staff(Role::ServiceManager);

        $this->actingAs($manager)->get(route('files.download', $passport))->assertForbidden(); // still a draft
        app(ApplicationReview::class)->submit($plan, $student);
        $this->actingAs($manager)->get(route('files.download', $passport))->assertOk();
        $this->get(route('files.download', $cv))->assertForbidden();

        app(ApplicationReview::class)->review($plan->refresh(), 'pass', null, $manager);
        $this->get(route('files.download', $passport))->assertForbidden(); // the review is over
    }

    public function test_a_returned_application_can_be_resubmitted_and_a_passed_one_shows_the_case_status(): void
    {
        $student = User::factory()->create();
        $cycle = $this->cycle();
        $plan = app(PlanService::class)->create($student, $cycle);
        $this->completeForm($student);
        $this->upload($student, 'passport');
        $review = app(ApplicationReview::class);
        $manager = $this->staff(Role::ServiceManager);

        $review->submit($plan, $student);
        $review->review($plan->refresh(), 'return', 'Add your degree.', $manager);
        $review->submit($plan->refresh(), $student);
        $this->assertSame('submitted', $plan->refresh()->stage);
        $this->assertNull($plan->review_remarks, 'the old remarks are cleared when it is sent again');

        $review->review($plan, 'pass', null, $manager);
        $case = app(CaseService::class)->open($student, $cycle, null, []);
        $this->assertSame('Review passed. New', $plan->refresh()->statusFor($case));
    }

    public function test_the_application_form_page_keeps_the_declaration_and_other_sections_together(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->put(route('student.form.update'), ['sections' => ['contact' => ['phone' => '+4912345']], 'declared' => '1'])->assertRedirect();
        $form = app(ApplicationFormService::class)->formFor($student);
        $this->assertNotNull($form->value('_declared_at'));
        $this->put(route('student.form.update'), ['sections' => ['contact' => ['phone' => '+4912345']]]);
        $this->assertNull($form->refresh()->value('_declared_at'), 'unticking the declaration withdraws it');
    }

    public function test_application_numbers_are_unique_and_an_other_students_application_is_forbidden(): void
    {
        $cycle = $this->cycle();
        $a = app(PlanService::class)->create(User::factory()->create(), $cycle);
        $b = app(PlanService::class)->create(User::factory()->create(), $cycle);
        $this->assertNotSame($a->application_no, $b->application_no);

        $this->actingAs($b->user);
        foreach (['applications.show', 'applications.form', 'applications.materials', 'applications.submit', 'applications.preview', 'applications.tools'] as $route) {
            $this->get(route('student.'.$route, $a))->assertForbidden();
        }
        $this->assertSame(2, ApplicationPlan::count());
    }
}

