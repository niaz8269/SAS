<?php

namespace Tests\Feature\Counselling;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Cases\CaseService;
use App\Domain\Counselling\Counselling;
use App\Domain\Counselling\CounsellorReport;
use App\Domain\Counselling\ScholarshipMatcher;
use App\Domain\Counselling\StudentGaps;
use App\Domain\Counselling\Timeline;
use App\Domain\Counselling\Workload;
use App\Domain\StudentWorkspace\PlanService;
use App\Enums\Role;
use App\Filament\Pages\CounsellorCalendar;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\ApplicationsRelationManager;
use App\Filament\Resources\Students\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Students\RelationManagers\MessagesRelationManager;
use App\Models\ApplicationPlan;
use App\Models\Audit;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\Document;
use App\Models\ReplyTemplate;
use App\Models\ServiceCase;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CaseUpdateNotification;
use App\Notifications\CounsellorDigest;
use App\Notifications\StudentWrote;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;
use ZipArchive;

class CounsellorExtrasTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Filament::setCurrentPanel('admin');
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents']);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function staff(Role $role, string $name = 'Staff'): User
    {
        return User::factory()->role($role)->create(['name' => $name]);
    }

    private function student(string $name, ?User $counsellor = null): User
    {
        $student = User::factory()->create(['name' => $name, 'email' => str($name)->slug().'@example.test']);
        $counsellor && app(Counselling::class)->assign($student, $counsellor);

        return $student;
    }

    private function upload(User $student, string $type = 'passport'): Document
    {
        $this->actingAs($student)->post('/student/documents', ['file' => UploadedFile::fake()->createWithContent($type.'.pdf', self::PDF), 'type' => $type, 'acknowledge' => '1'])->assertRedirect();

        return Document::where('user_id', $student->id)->where('type', $type)->latest('id')->firstOrFail();
    }

    public function test_today_lists_replies_due_follow_ups_quiet_students_deadlines_and_returned_applications(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $waiting = $this->student('Waiting Wendy', $counsellor);
        $due = $this->student('Due Dana', $counsellor);
        $quiet = $this->student('Quiet Quinn', $counsellor);
        $fine = $this->student('Fine Fatima', $counsellor);
        $returned = $this->student('Returned Rana', $counsellor);
        app(Counselling::class)->message($waiting, $waiting, 'Hello, I need help.');
        app(Counselling::class)->followUp($due, $counsellor, 'contacted', null, 'Call back', today()->subDay()->toDateString());
        app(Counselling::class)->followUp($fine, $counsellor, 'contacted', null, null, null);
        app(Counselling::class)->followUp($quiet, $counsellor, 'contacted', null, null, null)->forceFill(['created_at' => now()->subDays(10)])->save();
        $cycle = $this->publishedCycle([], $this->exactDeadline('+5 days'));
        $plan = app(PlanService::class)->create($fine, $cycle);
        app(PlanService::class)->create($returned, $this->publishedCycle())->forceFill(['stage' => 'returned', 'review_remarks' => 'Fix the passport scan'])->save();

        $work = app(Workload::class)->for($counsellor);
        $this->assertSame(['Waiting Wendy'], $work['replies']->map(fn ($r) => $r['student']->name)->all());
        $this->assertSame(['Due Dana'], $work['due']->map(fn ($r) => $r['student']->name)->all());
        $this->assertContains('Quiet Quinn', $work['stale']->map(fn ($r) => $r['student']->name)->all());
        $this->assertNotContains('Fine Fatima', $work['stale']->map(fn ($r) => $r['student']->name)->all());
        $this->assertSame(['Returned Rana'], $work['returned']->map(fn ($r) => $r['student']->name)->all());
        $this->assertSame($plan->id, $work['deadlines']->first()['plan']->id);

        $this->actingAs($counsellor)->get('/admin')->assertOk()->assertSee('Today')->assertSee('Waiting Wendy')->assertSee('Due Dana')->assertSee('Quiet Quinn');
        $this->assertSame(0, app(Workload::class)->for($this->staff(Role::Advisor, 'Counsellor Two'))['total'], 'another counsellor sees none of it');
    }

    public function test_the_daily_digest_goes_only_to_counsellors_with_something_to_do_and_a_student_message_alerts_the_counsellor(): void
    {
        Notification::fake();
        $busy = $this->staff(Role::Advisor, 'Busy');
        $idle = $this->staff(Role::Advisor, 'Idle');
        $wendy = $this->student('Waiting Wendy', $busy);
        app(Counselling::class)->message($wendy, $wendy, 'Please help me choose.');
        Notification::assertSentTo($busy, StudentWrote::class);
        Notification::assertNotSentTo($idle, StudentWrote::class);

        $this->artisan('sas:counsellor-digest')->assertSuccessful();
        Notification::assertSentTo($busy, CounsellorDigest::class);
        Notification::assertNotSentTo($idle, CounsellorDigest::class);

        // A student with no counsellor reaches the service managers instead.
        $manager = $this->staff(Role::Administrator, 'Manager');
        $lone = $this->student('Lone Lena');
        app(Counselling::class)->message($lone, $lone, 'Is anyone there?');
        Notification::assertSentTo($manager, StudentWrote::class);
    }

    public function test_status_updates_can_carry_a_follow_up_date_and_the_list_filters_on_it_and_on_goals(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $amina = $this->student('Amina Khan', $counsellor);
        $bilal = $this->student('Bilal Shah', $counsellor);
        $this->actingAs($counsellor);

        Livewire::test(ViewStudent::class, ['record' => $amina->id])->callAction('updateStatus', ['status' => 'contacted', 'comment' => 'Call again', 'follow_up_on' => today()->toDateString()])->assertHasNoActionErrors();
        $this->assertTrue($amina->fresh()->latestFollowUp->follow_up_on->isToday());
        Livewire::test(ViewStudent::class, ['record' => $amina->id])->callAction('goals', ['countries' => 'China, Turkey', 'degree' => "Master's", 'subject' => 'Software engineering', 'budget' => 'Full scholarship', 'intake' => 'September 2027'])->assertHasNoActionErrors();
        $this->assertSame(['China', 'Turkey'], $amina->fresh()->goals->countryList());

        Livewire::test(ListStudents::class)->filterTable('goal', ['country' => 'turkey', 'degree' => ''])->assertCanSeeTableRecords([$amina]);
        Livewire::test(ListStudents::class)->filterTable('goal', ['country' => 'turkey', 'degree' => ''])->assertCanNotSeeTableRecords([$bilal]);
        Livewire::test(ListStudents::class)->filterTable('follow_up_due')->assertCanSeeTableRecords([$amina]);
        Livewire::test(ListStudents::class)->filterTable('follow_up_due')->assertCanNotSeeTableRecords([$bilal]);
    }

    public function test_the_gaps_panel_and_the_reminder_name_what_is_missing(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $amina = $this->student('Amina Khan', $counsellor);
        $cycle = $this->publishedCycle([], null, null, DegreeLevel::where('code', 'master')->first());
        app(PlanService::class)->create($amina, $cycle);

        $gaps = app(StudentGaps::class)->for($amina);
        $this->assertContains('Surname (as in passport)', $gaps['form']);
        $this->assertNotEmpty($gaps['applications'][0]['missing']);
        $this->assertGreaterThan(5, $gaps['count']);

        $this->actingAs($counsellor);
        Livewire::test(ViewStudent::class, ['record' => $amina->id])->assertSee('Still missing')->callAction('remind')->assertHasNoActionErrors();
        $message = $amina->counsellorMessages()->latest('id')->first();
        $this->assertSame($counsellor->id, $message->author_id);
        $this->assertStringContainsString('Surname (as in passport)', $message->body);
        $this->assertStringContainsString('Amina Khan', $message->body);
    }

    public function test_scholarships_are_suggested_from_the_goals_and_sent_as_a_shortlist(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $amina = $this->student('Amina Khan', $counsellor);
        $china = Country::where('name', 'China')->firstOrFail();
        $fit = $this->publishedCycle([], $this->exactDeadline('+60 days'), $china, DegreeLevel::where('code', 'master')->first());
        $other = $this->publishedCycle([], $this->exactDeadline('+60 days'), Country::where('name', 'Peru')->firstOrFail(), DegreeLevel::where('code', 'bachelor')->first());

        $this->assertTrue(app(ScholarshipMatcher::class)->for($amina)->isEmpty(), 'no goals, no suggestions');
        app(Counselling::class)->setGoals($amina, $counsellor, ['countries' => 'China', 'degree' => 'Master', 'subject' => '', 'budget' => '', 'intake' => '']);
        $matches = app(ScholarshipMatcher::class)->for($amina->fresh());
        $this->assertSame([$fit->id], $matches->pluck('cycle.id')->all());
        $this->assertNotContains($other->id, $matches->pluck('cycle.id')->all());

        $this->actingAs($counsellor);
        Livewire::test(ViewStudent::class, ['record' => $amina->id])->callAction('matches', ['cycles' => [$fit->id]])->assertHasNoActionErrors();
        $this->assertStringContainsString($fit->displayName(), $amina->counsellorMessages()->latest('id')->first()->body);

        // Already applied: no longer suggested.
        app(PlanService::class)->create($amina, $fit);
        $this->assertTrue(app(ScholarshipMatcher::class)->for($amina->fresh())->isEmpty());
    }

    public function test_the_timeline_collects_what_happened(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $amina = $this->student('Amina Khan', $counsellor);
        $this->upload($amina);
        app(PlanService::class)->create($amina, $this->publishedCycle());
        app(Counselling::class)->followUp($amina, $counsellor, 'contacted', 'x', 'Called her');
        app(Counselling::class)->message($amina, $amina, 'Hello');

        $kinds = app(Timeline::class)->for($amina)->pluck('kind')->unique()->all();
        foreach (['Message', 'Status', 'Document', 'Application', 'Counsellor', 'Account'] as $kind) {
            $this->assertContains($kind, $kinds);
        }
        $this->actingAs($counsellor);
        Livewire::test(ViewStudent::class, ['record' => $amina->id])->assertActionExists('timeline');
    }

    public function test_a_counsellor_sends_an_application_to_review_or_decides_on_it_for_their_student_only(): void
    {
        Notification::fake();
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $other = $this->staff(Role::Advisor, 'Counsellor Two');
        $amina = $this->student('Amina Khan', $counsellor);
        $plan = app(PlanService::class)->create($amina, $this->publishedCycle());

        $this->actingAs($counsellor);
        $table = fn () => Livewire::test(ApplicationsRelationManager::class, ['ownerRecord' => $amina, 'pageClass' => ViewStudent::class]);
        $table()->assertCanSeeTableRecords([$plan])->callTableAction('send', $plan);
        $this->assertSame('draft', $plan->fresh()->stage, 'an incomplete application is not sent');

        $plan->forceFill(['stage' => 'submitted', 'review_requested_at' => now()])->save();
        $table()->callTableAction('return', $plan, ['remarks' => 'Please upload a clear passport scan.']);
        $this->assertSame('returned', $plan->fresh()->stage);
        $this->assertSame($counsellor->id, $plan->fresh()->reviewed_by);

        $plan->refresh()->forceFill(['stage' => 'submitted'])->save();
        $table()->callTableAction('pass', $plan, ['remarks' => 'All good.']);
        $this->assertSame('passed', $plan->fresh()->stage);

        // Not their student: no access to the screen's records at all.
        $this->assertFalse($other->can('review', $plan->fresh()->forceFill(['stage' => 'submitted'])));
        $this->assertFalse($other->can('submitForReview', $plan->fresh()));
    }

    public function test_document_comments_reach_the_student_and_the_zip_holds_every_file(): void
    {
        Notification::fake();
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $amina = $this->student('Amina Khan', $counsellor);
        $passport = $this->upload($amina, 'passport');
        $this->upload($amina, 'transcript');

        $this->actingAs($counsellor);
        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $amina, 'pageClass' => ViewStudent::class])->callTableAction('comment', $passport, ['body' => 'The scan is too dark, please upload it again.']);
        $this->assertSame(1, $passport->comments()->count());
        Notification::assertSentTo($amina, CaseUpdateNotification::class);
        $this->actingAs($amina)->get(route('student.documents.index'))->assertSee('The scan is too dark, please upload it again.');
        $plan = app(PlanService::class)->create($amina, $this->publishedCycle());
        $this->get(route('student.applications.materials', $plan))->assertSee('The scan is too dark');

        $response = $this->actingAs($counsellor)->get(route('students.documents.zip', $amina->public_id))->assertOk();
        $this->assertSame('application/zip', $response->headers->get('content-type'));
        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($file);
        $this->assertSame(2, $zip->numFiles);
        $zip->close();
        $this->assertTrue(Audit::where('action', 'student.documents_zipped')->exists());

        $this->actingAs($this->staff(Role::Advisor, 'Counsellor Two'))->get(route('students.documents.zip', $amina->public_id))->assertForbidden();
        $this->actingAs($this->staff(Role::Administrator, 'Manager'))->get(route('students.documents.zip', $amina->public_id))->assertForbidden();
    }

    public function test_reply_templates_are_shared_or_private_and_fill_in_the_names(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $other = $this->staff(Role::Advisor, 'Counsellor Two');
        $manager = $this->staff(Role::Administrator, 'Manager');
        $shared = new ReplyTemplate(['title' => 'Welcome', 'body' => 'Hello {name}, I am {counsellor}.']);
        $shared->forceFill(['owner_id' => null])->save();
        $mine = new ReplyTemplate(['title' => 'Mine', 'body' => 'Private']);
        $mine->forceFill(['owner_id' => $counsellor->id])->save();

        $this->assertSame(['Mine', 'Welcome'], ReplyTemplate::usableBy($counsellor)->orderBy('title')->pluck('title')->all());
        $this->assertSame(['Welcome'], ReplyTemplate::usableBy($other)->pluck('title')->all());
        $amina = $this->student('Amina Khan', $counsellor);
        $this->assertSame('Hello Amina Khan, I am Counsellor One.', $shared->fill_for($amina, $counsellor));
        $this->assertTrue($counsellor->can('update', $mine));
        $this->assertFalse($counsellor->can('update', $shared));
        $this->assertTrue($manager->can('update', $shared));
        $this->assertFalse($other->can('view', $mine));

        $this->actingAs($counsellor);
        Livewire::test(MessagesRelationManager::class, ['ownerRecord' => Student::find($amina->id), 'pageClass' => ViewStudent::class])
            ->assertSee('Write to the student');
    }

    public function test_bulk_actions_update_many_students_and_only_managers_assign(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $manager = $this->staff(Role::Administrator, 'Manager');
        $a = $this->student('Amina Khan', $counsellor);
        $b = $this->student('Bilal Shah', $counsellor);
        $c = $this->student('Carla Free');

        $this->actingAs($counsellor);
        Livewire::test(ListStudents::class)->callTableBulkAction('bulkStatus', [$a, $b], ['status' => 'documents', 'comment' => 'Chasing documents'])->assertHasNoTableBulkActionErrors();
        $this->assertSame('documents', $a->fresh()->latestFollowUp->status);
        $this->assertSame('documents', $b->fresh()->latestFollowUp->status);
        Livewire::test(ListStudents::class)->assertTableBulkActionHidden('bulkAssign');

        $this->actingAs($manager);
        Livewire::test(ListStudents::class)->callTableBulkAction('bulkAssign', [$c], ['counsellor_id' => $counsellor->id]);
        $this->assertSame($counsellor->id, $c->fresh()->counsellor_id);
    }

    public function test_the_report_is_for_managers_and_the_calendar_for_counsellors(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $manager = $this->staff(Role::Administrator, 'Manager');
        $amina = $this->student('Amina Khan', $counsellor);
        app(Counselling::class)->message($amina, $amina, 'Hello');
        app(Counselling::class)->message($amina, $counsellor, 'Hi Amina');
        app(Counselling::class)->followUp($amina, $counsellor, 'offer', null, 'Offer in', today()->addDays(3)->toDateString());

        $row = app(CounsellorReport::class)->rows()->firstWhere('counsellor.id', $counsellor->id);
        $this->assertSame(1, $row['students']);
        $this->assertSame(1, $row['offers']);
        $this->assertNotNull($row['reply_hours']);

        $this->actingAs($manager)->get('/admin/counsellor-report')->assertOk()->assertSee('Counsellor One');
        $this->actingAs($counsellor)->get('/admin/counsellor-report')->assertForbidden();
        $this->actingAs(User::factory()->role(Role::PartnerManager)->create())->get('/admin/my-calendar')->assertForbidden();
        $this->actingAs($counsellor)->get('/admin/my-calendar')->assertOk()->assertSee('Follow-up')->assertSee('Amina Khan');
        $this->assertSame(1, collect((new CounsellorCalendar)->agenda())->flatten(1)->where('kind', 'Follow-up')->count());
    }

    public function test_a_counsellor_never_reaches_a_students_applications_from_the_list_of_someone_else(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $amina = $this->student('Amina Khan');
        $plan = app(PlanService::class)->create($amina, $this->publishedCycle());
        $this->actingAs($counsellor)->get(route('review.download', $plan->public_id))->assertForbidden();
        ServiceCase::query()->count(); // a case advisor counts as a counsellor
        app(CaseService::class)->open($amina, null, $counsellor, ['title' => 'Case']);
        $this->actingAs($counsellor)->get(route('review.download', $plan->public_id))->assertOk();
        $this->assertInstanceOf(ApplicationPlan::class, $plan);
        $this->assertNotNull(app(ApplicationFormService::class));
    }
}
