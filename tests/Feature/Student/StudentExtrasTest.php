<?php

namespace Tests\Feature\Student;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Counselling\Counselling;
use App\Domain\Counselling\ScholarshipMatcher;
use App\Domain\Counselling\Workload;
use App\Domain\Documents\DocumentHealth;
use App\Domain\Documents\DocumentService;
use App\Domain\Journey\Journey;
use App\Domain\StudentWorkspace\PlanService;
use App\Enums\Role;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\ApplicationsRelationManager;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\Document;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

/** The journey after review, document health, photos to PDF, recommendations and message attachments. */
class StudentExtrasTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Filament::setCurrentPanel('admin');
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents']);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function counsellor(string $name = 'Counsellor One'): User
    {
        return User::factory()->role(Role::Advisor)->create(['name' => $name]);
    }

    public function test_the_journey_follows_the_application_then_the_counsellor_moves_it_and_the_student_ticks_tasks(): void
    {
        $counsellor = $this->counsellor();
        $student = User::factory()->create(['name' => 'Amina Khan']);
        app(Counselling::class)->assign($student, $counsellor);
        $plan = app(PlanService::class)->create($student, $this->publishedCycle());
        $journey = app(Journey::class);

        $this->assertSame('applying', $journey->currentStage($plan));
        $plan->forceFill(['stage' => 'submitted'])->save();
        $this->assertSame('review', $journey->currentStage($plan));
        $plan->forceFill(['stage' => 'passed'])->save();
        $this->assertSame('ready', $journey->currentStage($plan->fresh()));

        $this->actingAs($counsellor);
        Livewire::test(ApplicationsRelationManager::class, ['ownerRecord' => Student::find($student->id), 'pageClass' => ViewStudent::class])
            ->callTableAction('moveStage', $plan, ['stage' => 'offer'])
            ->callTableAction('addTask', $plan, ['title' => 'Upload the signed acceptance', 'due_on' => today()->subDay()->toDateString()]);
        $plan->refresh();
        $this->assertSame('offer', $journey->currentStage($plan));
        $tasks = $plan->journeyTasks()->get();
        $this->assertTrue($tasks->contains('title', 'Accept or decline before the date on the offer'), "the stage's standard tasks are added");
        $custom = $tasks->firstWhere('title', 'Upload the signed acceptance');
        $this->assertTrue($custom->isOverdue());

        // Moving to the same stage again does not duplicate its tasks.
        $journey->setStage($plan, 'offer', $counsellor);
        $this->assertSame(count($tasks), $plan->journeyTasks()->count());

        $this->actingAs($student)->get(route('student.applications.journey', $plan))->assertOk()->assertSee('Offer received')->assertSee('You are here')->assertSee('Upload the signed acceptance')->assertSee('Overdue');
        $this->get(route('student.applications.index'))->assertSee('past the date')->assertSee('Journey:');
        $this->patch(route('student.applications.journey.tasks.update', [$plan, $custom]), ['done' => 1])->assertRedirect();
        $this->assertTrue($custom->fresh()->isDone());
        $this->assertSame($student->id, $custom->fresh()->done_by);
        $this->patch(route('student.applications.journey.tasks.update', [$plan, $custom]), ['done' => 0]);
        $this->assertFalse($custom->fresh()->isDone());

        // Someone else's application is out of reach, and so is moving a draft along.
        $this->actingAs(User::factory()->create())->get(route('student.applications.journey', $plan))->assertForbidden();
        $draft = app(PlanService::class)->create($student, $this->publishedCycle());
        $this->actingAs($counsellor);
        $this->assertFalse($counsellor->can('manageJourney', $draft));
        $this->expectException(\InvalidArgumentException::class);
        $journey->setStage($draft, 'offer', $counsellor);
    }

    public function test_the_counsellors_today_list_shows_student_tasks_past_their_date(): void
    {
        $counsellor = $this->counsellor();
        $student = User::factory()->create(['name' => 'Late Lina']);
        app(Counselling::class)->assign($student, $counsellor);
        $plan = app(PlanService::class)->create($student, $this->publishedCycle());
        $plan->forceFill(['stage' => 'passed'])->save();
        app(Journey::class)->addTask($plan, 'Book the medical check', today()->subDays(2)->toDateString(), $counsellor);

        $work = app(Workload::class)->for($counsellor);
        $this->assertSame(['Book the medical check'], $work['tasks']->map(fn ($r) => $r['task']->title)->all());
        $this->actingAs($counsellor)->get('/admin')->assertSee('Student tasks past their date')->assertSee('Late Lina');
    }

    public function test_the_passport_warning_appears_when_it_expires_too_soon(): void
    {
        $student = User::factory()->create();
        $forms = app(ApplicationFormService::class);
        $health = app(DocumentHealth::class);

        $this->assertNull($health->passport($student), 'nothing to warn about without a date');
        $forms->saveAll($student, ['personal' => ['passport_expiry' => now()->subMonth()->toDateString()]]);
        $this->assertSame('urgent', $health->passport($student)['level']);
        $forms->saveAll($student, ['personal' => ['passport_expiry' => now()->addMonths(8)->toDateString()]]);
        $this->assertSame('urgent', $health->passport($student)['level']);
        $forms->saveAll($student, ['personal' => ['passport_expiry' => now()->addMonths(15)->toDateString()]]);
        $this->assertSame('soon', $health->passport($student)['level']);
        $forms->saveAll($student, ['personal' => ['passport_expiry' => now()->addYears(4)->toDateString()]]);
        $this->assertNull($health->passport($student));

        $forms->saveAll($student, ['personal' => ['passport_expiry' => now()->addMonths(5)->toDateString()]]);
        $this->actingAs($student)->get(route('student.applications.index'))->assertSee('Your passport expires on');
    }

    public function test_small_photos_get_a_gentle_note_and_several_photos_can_become_one_pdf(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student);

        $this->post('/student/documents', ['file' => UploadedFile::fake()->image('tiny.jpg', 300, 200), 'type' => 'passport', 'acknowledge' => '1'])->assertRedirect();
        $this->post('/student/documents', ['file' => UploadedFile::fake()->image('big.jpg', 1600, 1200)->size(300), 'type' => 'transcript', 'acknowledge' => '1'])->assertRedirect();
        $this->assertNotNull(Document::where('type', 'passport')->first()->currentVersion->quality_note);
        $this->assertNull(Document::where('type', 'transcript')->first()->currentVersion->quality_note);

        $this->post('/student/documents', ['files' => [UploadedFile::fake()->image('p1.jpg', 1600, 1200)->size(300), UploadedFile::fake()->image('p2.png', 1600, 1200)->size(300)], 'combine' => '1', 'type' => 'degree_certificate', 'acknowledge' => '1'])->assertRedirect();
        $docs = Document::where('type', 'degree_certificate')->get();
        $this->assertCount(1, $docs, 'two photos, one document');
        $this->assertSame('application/pdf', $docs->first()->currentVersion->mime);
        $this->assertStringStartsWith('%PDF', Storage::disk('private_documents')->get(app(DocumentService::class)->pathFor($docs->first()->currentVersion->fresh())));

        // A PDF among the photos cannot be combined.
        $this->post('/student/documents', ['files' => [UploadedFile::fake()->image('p1.jpg', 1600, 1200), UploadedFile::fake()->createWithContent('x.pdf', "%PDF-1.4\n%%EOF")], 'combine' => '1', 'type' => 'cv', 'acknowledge' => '1'])->assertSessionHasErrors('files');
    }

    public function test_choose_scholarships_recommends_what_fits_the_students_own_form(): void
    {
        $student = User::factory()->create();
        $china = Country::where('name', 'China')->firstOrFail();
        $master = DegreeLevel::where('code', 'master')->first();
        $fit = $this->publishedCycle([], $this->exactDeadline('+60 days'), $china, $master);
        $other = $this->publishedCycle([], $this->exactDeadline('+60 days'), Country::where('name', 'Peru')->firstOrFail(), DegreeLevel::where('code', 'bachelor')->first());
        $this->actingAs($student);

        $this->get(route('student.choose.index'))->assertOk()->assertDontSee('Recommended for you', false);
        app(ApplicationFormService::class)->saveAll($student, ['study_plan' => ['apply_as' => "Master's", 'major' => 'Engineering', 'discipline' => 'Engineering']]);
        [$countries, $degree] = app(ScholarshipMatcher::class)->criteria($student->fresh());
        $this->assertSame([], $countries);
        $this->assertSame("master's", $degree);
        $this->get(route('student.choose.index'))->assertOk()->assertSee('Recommended for you')->assertSee($fit->programme->title)
            ->assertSee('Fits: ');
        $this->get(route('student.choose.index', ['q' => 'x']))->assertDontSee('Recommended for you');
        $this->assertNotNull($other);
    }

    public function test_a_file_can_travel_with_a_message_in_both_directions(): void
    {
        $counsellor = $this->counsellor();
        $student = User::factory()->create(['name' => 'Amina Khan']);
        app(Counselling::class)->assign($student, $counsellor);

        $this->actingAs($student)->post(route('student.counsellor.store'), ['body' => 'Here is my transcript.', 'attachment' => UploadedFile::fake()->createWithContent('transcript.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF")])->assertRedirect();
        $message = $student->counsellorMessages()->first();
        $this->assertNotNull($message->document_id);
        $this->assertSame($student->id, $message->document->user_id, 'the file is among the student\'s own documents');

        $this->get(route('student.counsellor'))->assertSee('transcript.pdf')->assertSee('Chat on WhatsApp');
        $this->actingAs($counsellor)->get(route('files.download', $message->document))->assertOk();

        $reply = app(Counselling::class)->message($student, $counsellor, 'Please sign and return this.', UploadedFile::fake()->createWithContent('form.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF"));
        $this->assertSame($student->id, $reply->document->user_id);
        $this->actingAs($student)->get(route('student.counsellor'))->assertSee('form.pdf');

        $this->post(route('student.counsellor.store'), ['body' => 'Bad file', 'attachment' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors();
    }
}
