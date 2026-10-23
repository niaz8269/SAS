<?php

namespace Tests\Feature\Cases;

use App\Domain\Cases\CaseService;
use App\Domain\StudentWorkspace\PlanService;
use App\Enums\Role;
use App\Filament\Resources\ServiceCases\Pages\EditServiceCase;
use App\Filament\Resources\ServiceCases\RelationManagers\MessagesRelationManager;
use App\Models\CaseDocumentShare;
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

        $this->actingAs($student)->post(route('student.cases.messages.store', $case), ['body' => 'Which transcript do you need?'])->assertRedirect();
        $this->assertTrue($case->refresh()->awaiting_reply);

        $this->actingAs($advisor);
        Livewire::test(MessagesRelationManager::class, ['ownerRecord' => $case, 'pageClass' => EditServiceCase::class])
            ->callTableAction('reply', null, ['visibility' => 'internal', 'body' => 'Student seems unsure, call them.']);
        $this->assertTrue($case->refresh()->awaiting_reply, 'an internal note is not a reply');
        $told = $student->notifications()->count();

        Livewire::test(MessagesRelationManager::class, ['ownerRecord' => $case, 'pageClass' => EditServiceCase::class])
            ->callTableAction('reply', null, ['visibility' => 'public', 'body' => 'The official one, please.']);
        $this->assertFalse($case->refresh()->awaiting_reply);
        $this->assertSame($told + 1, $student->notifications()->count(), 'only the public reply notifies the student');

        $this->actingAs($student)->get(route('student.cases.show', $case))->assertOk()
            ->assertSee('Which transcript do you need?')->assertSee('The official one, please.')->assertDontSee('call them');
    }

    public function test_a_student_cannot_message_or_share_on_someone_elses_case(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $case = app(CaseService::class)->open($owner, null, null, ['title' => 'Private']);

        $this->actingAs($intruder)->post(route('student.cases.messages.store', $case), ['body' => 'hello there'])->assertForbidden();
        $this->actingAs($intruder)->put(route('student.cases.documents.update', $case), ['documents' => [1], 'consent' => '1'])->assertForbidden();
        $this->assertSame(0, $case->messages()->count());
    }

    public function test_sharing_needs_consent_and_only_the_students_own_documents_can_be_shared(): void
    {
        $student = User::factory()->create();
        $stranger = User::factory()->create();
        $mine = $this->upload($student);
        $theirs = $this->upload($stranger, 'Passport');
        $case = app(CaseService::class)->open($student, null, null, ['title' => 'Share']);

        $this->actingAs($student)->put(route('student.cases.documents.update', $case), ['documents' => [$mine->id]])->assertSessionHasErrors('consent');
        $this->assertSame(0, CaseDocumentShare::count());

        $this->actingAs($student)->put(route('student.cases.documents.update', $case), ['documents' => [$mine->id, $theirs->id], 'consent' => '1'])->assertRedirect();
        $this->assertSame([$mine->id], CaseDocumentShare::pluck('document_id')->all(), "another person's document is ignored");
        $this->assertStringContainsString('only to prepare my application', CaseDocumentShare::first()->consent_text);

        $this->actingAs($student)->put(route('student.cases.documents.update', $case), ['documents' => []])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, CaseDocumentShare::count());
        $this->assertSame(0, $case->shares()->count(), 'no active shares remain');
        $this->assertNotNull(CaseDocumentShare::first()->revoked_at, 'unticking stops the sharing');
    }

    public function test_the_assigned_counsellor_opens_shared_documents_only_while_shared(): void
    {
        $student = User::factory()->create();
        $doc = $this->upload($student);
        $private = $this->upload($student, 'Passport');
        $assigned = $this->staff(Role::Advisor);
        $otherAdvisor = $this->staff(Role::Advisor);
        $manager = $this->staff(Role::ServiceManager);
        $editor = $this->staff(Role::Editor);
        $service = app(CaseService::class);
        $case = $service->open($student, null, $assigned, ['title' => 'Docs']);
        $service->syncShares($case, $student, [$doc->id], 'consent');

        $this->actingAs($assigned)->get(route('files.download', $doc))->assertOk();
        $this->actingAs($manager)->get(route('files.download', $doc))->assertOk();
        $this->actingAs($assigned)->get(route('files.download', $private))->assertForbidden();
        $this->actingAs($otherAdvisor)->get(route('files.download', $doc))->assertForbidden();
        $this->actingAs($editor)->get(route('files.download', $doc))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('files.download', $doc))->assertForbidden();

        $service->syncShares($case, $student, [], 'consent');
        $this->actingAs($assigned)->get(route('files.download', $doc))->assertForbidden();
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

    public function test_the_share_and_message_sections_render(): void
    {
        $student = User::factory()->create();
        $this->upload($student, 'Degree certificate');
        $case = app(CaseService::class)->open($student, null, null, ['title' => 'Render']);

        $this->actingAs($student)->get(route('student.cases.show', $case))->assertOk()
            ->assertSee('Messages with your counsellor')->assertSee('Documents shared with your counsellor')->assertSee('Degree certificate')->assertSee('I can stop sharing at any time');
    }
}
