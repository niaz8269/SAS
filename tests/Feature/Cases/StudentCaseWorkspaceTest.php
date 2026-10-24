<?php

namespace Tests\Feature\Cases;

use App\Domain\Cases\CaseService;
use App\Domain\StudentWorkspace\PlanService;
use App\Enums\Role;
use App\Filament\Resources\ServiceCases\Pages\EditServiceCase;
use App\Filament\Resources\ServiceCases\RelationManagers\MessagesRelationManager;
use App\Models\Document;
use App\Models\ScholarshipCycle;
use App\Models\User;
use App\Notifications\CaseUpdateNotification;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StudentCaseWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    private function staff(Role $role): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();

        return $user;
    }

    private function upload(User $student, string $title = 'Transcript'): Document
    {
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents']);
        $this->actingAs($student)->post('/student/documents', ['file' => UploadedFile::fake()->createWithContent('t.pdf', self::PDF), 'type' => 'transcript', 'title' => $title, 'acknowledge' => '1'])->assertRedirect();

        return Document::where('user_id', $student->id)->where('title', $title)->firstOrFail();
    }

    public function test_the_student_dashboard_summarises_cases_and_fees(): void
    {
        $student = User::factory()->create();
        app(CaseService::class)->open($student, null, null, ['title' => 'Chevening application']);

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()
            ->assertSee('SAS is helping you with')->assertSee('Chevening application')->assertSeeText('US$100 to pay');
    }

    public function test_case_changes_notify_the_student_by_site_and_email_without_leaking_to_others(): void
    {
        Notification::fake();
        $student = User::factory()->create();
        $other = User::factory()->create();
        $advisor = $this->staff(Role::Advisor);
        $service = app(CaseService::class);

        $case = $service->open($student, null, null, ['title' => 'DAAD']);
        $service->assign($case, $advisor);
        $service->setStatus($case, 'in_progress');
        $service->markPaid($case->invoices()->first(), null, 'WIRE-1');

        Notification::assertSentTo($student, CaseUpdateNotification::class, fn ($n) => str_contains($n->message, 'now your counsellor'));
        Notification::assertSentTo($student, CaseUpdateNotification::class, fn ($n) => str_contains($n->message, 'In progress'));
        Notification::assertSentTo($student, CaseUpdateNotification::class, fn ($n) => str_contains($n->message, 'recorded your payment'));
        Notification::assertNothingSentTo($other);
    }

    public function test_a_student_who_turned_off_case_emails_still_gets_the_in_app_notice(): void
    {
        $student = User::factory()->create();
        $service = app(CaseService::class);
        $this->actingAs($student)->put(route('student.notifications.update'), ['pref' => ['transactional' => ['in_app' => '1']]]);

        $case = $service->open($student, null, null, ['title' => 'Prefs']);
        $service->setStatus($case, 'in_progress');

        $this->assertSame(2, $student->notifications()->count());
        $this->actingAs($student)->get(route('student.notifications.index'))->assertOk()->assertSee('Your case "Prefs" is now: In progress.')->assertSee('View your case');
    }

    public function test_student_messages_flag_the_case_and_a_staff_reply_clears_it_and_notifies(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $student = User::factory()->create();
        $advisor = $this->staff(Role::Advisor);
        $case = app(CaseService::class)->open($student, null, $advisor, ['title' => 'Messages']);

        $this->actingAs($student)->post(route('student.counsellor.store'), ['body' => 'Which transcript do you need?'])->assertRedirect();
        $this->assertTrue($case->refresh()->awaiting_reply, 'the one conversation flags the open case');

        $this->actingAs($advisor);
        Livewire::test(MessagesRelationManager::class, ['ownerRecord' => $case, 'pageClass' => EditServiceCase::class])
            ->callTableAction('reply', null, ['visibility' => 'internal', 'body' => 'Student seems unsure, call them.']);
        $this->assertTrue($case->refresh()->awaiting_reply, 'an internal note is not a reply');
        $told = $student->notifications()->count();

        Livewire::test(MessagesRelationManager::class, ['ownerRecord' => $case, 'pageClass' => EditServiceCase::class])
            ->callTableAction('reply', null, ['visibility' => 'public', 'body' => 'The official one, please.']);
        $this->assertFalse($case->refresh()->awaiting_reply);
        $this->assertSame($told + 1, $student->notifications()->count(), 'only the public reply notifies the student');
        $this->assertSame(1, $case->messages()->count(), 'a reply goes to the conversation, only the note stays on the case');

        $this->actingAs($student)->get(route('student.counsellor'))->assertOk()
            ->assertSee('Which transcript do you need?')->assertSee('The official one, please.')->assertDontSee('call them');
        $this->get(route('student.cases.show', $case))->assertOk()->assertDontSee('call them')->assertSee(route('student.counsellor'));
    }

    public function test_a_student_cannot_open_someone_elses_case_and_the_old_share_routes_are_gone(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $case = app(CaseService::class)->open($owner, null, null, ['title' => 'Private']);

        $this->actingAs($intruder)->get(route('student.cases.show', $case))->assertForbidden();
        $this->assertFalse(Route::has('student.cases.documents.update'));
        $this->assertFalse(Route::has('student.cases.form.update'));
        $this->assertFalse(Route::has('student.cases.messages.store'));
    }

    public function test_a_student_can_no_longer_write_in_a_case_only_staff_notes_and_replies(): void
    {
        $student = User::factory()->create();
        $case = app(CaseService::class)->open($student, null, null, ['title' => 'Mine']);

        $this->expectException(\InvalidArgumentException::class);
        app(CaseService::class)->postMessage($case, $student, 'hello');
    }

    public function test_the_students_counsellor_opens_their_documents_and_managers_only_what_is_shared(): void
    {
        $student = User::factory()->create();
        $doc = $this->upload($student);
        $private = $this->upload($student, 'Passport');
        $assigned = $this->staff(Role::Advisor);
        $otherAdvisor = $this->staff(Role::Advisor);
        $manager = $this->staff(Role::Administrator);
        $editor = $this->staff(Role::Advisor);
        $service = app(CaseService::class);
        $case = $service->open($student, null, $assigned, ['title' => 'Docs']);
        $service->syncShares($case, $student, [$doc->id], 'consent');

        $this->actingAs($assigned)->get(route('files.download', $doc))->assertOk();
        $this->actingAs($manager)->get(route('files.download', $doc))->assertOk();
        $this->actingAs($assigned)->get(route('files.download', $private))->assertOk(); // the student's own counsellor reads every document, shared or not
        $this->actingAs($manager)->get(route('files.download', $private))->assertForbidden(); // a manager only gets what is shared
        $this->actingAs($otherAdvisor)->get(route('files.download', $doc))->assertForbidden();
        $this->actingAs($editor)->get(route('files.download', $doc))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('files.download', $doc))->assertForbidden();

        $service->syncShares($case, $student, [], 'consent');
        $this->actingAs($manager)->get(route('files.download', $doc))->assertForbidden();
        $this->actingAs($assigned)->get(route('files.download', $doc))->assertOk();
        $this->actingAs($student)->get(route('student.documents.download', $doc))->assertOk();
    }

    public function test_a_plan_and_its_case_point_at_each_other(): void
    {
        $this->seed(TaxonomySeeder::class);
        $student = User::factory()->create();
        $cycle = ScholarshipCycle::factory()->published()->create();
        $plan = app(PlanService::class)->create($student, $cycle);
        $case = app(CaseService::class)->open($student, $cycle, null, []);

        $this->actingAs($student)->get(route('student.applications.show', $plan))->assertOk()->assertSee('SAS is helping with this application')->assertSee(route('student.cases.show', $case));
        $this->get(route('student.applications.index'))->assertOk()->assertSee($plan->application_no);
        $this->get(route('student.cases.show', $case))->assertOk()->assertSee(route('student.applications.materials', $plan));
    }

    public function test_the_case_page_says_who_sees_the_details_and_has_no_sharing_boxes(): void
    {
        $student = User::factory()->create();
        $this->upload($student, 'Degree certificate');
        $case = app(CaseService::class)->open($student, null, null, ['title' => 'Render']);

        $this->actingAs($student)->get(route('student.cases.show', $case))->assertOk()
            ->assertSee('Who can see your details')->assertSee('Open my conversation')
            ->assertDontSee('can only read what you share')->assertDontSee('I can stop sharing at any time')->assertDontSee('name="consent"', false);
    }
}
