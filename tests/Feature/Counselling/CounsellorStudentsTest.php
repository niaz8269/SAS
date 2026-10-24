<?php

namespace Tests\Feature\Counselling;

use App\Domain\Counselling\Counselling;
use App\Domain\StudentWorkspace\PlanService;
use App\Enums\Role;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Models\Audit;
use App\Models\Document;
use App\Models\StudentFollowUp;
use App\Models\User;
use App\Notifications\CaseUpdateNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class CounsellorStudentsTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents']);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function staff(Role $role, string $name): User
    {
        return User::factory()->role($role)->create(['name' => $name]);
    }

    private function student(string $name, ?User $counsellor = null): User
    {
        $student = User::factory()->create(['name' => $name, 'email' => str($name)->slug().'@example.test']);
        $counsellor && app(Counselling::class)->assign($student, $counsellor);

        return $student;
    }

    private function uploadPassport(User $student): Document
    {
        $this->actingAs($student)->post('/student/documents', ['file' => UploadedFile::fake()->createWithContent('passport.pdf', self::PDF), 'type' => 'passport', 'acknowledge' => '1'])->assertRedirect();

        return Document::where('user_id', $student->id)->where('type', 'passport')->firstOrFail();
    }

    public function test_a_counsellor_sees_only_the_students_assigned_to_them(): void
    {
        $mine = $this->staff(Role::Advisor, 'Counsellor One');
        $other = $this->staff(Role::Advisor, 'Counsellor Two');
        $amina = $this->student('Amina Khan', $mine);
        $bilal = $this->student('Bilal Shah', $other);
        $carla = $this->student('Carla Unassigned');

        $this->actingAs($mine);
        Livewire::test(ListStudents::class)->assertCanSeeTableRecords([$amina])->assertCanNotSeeTableRecords([$bilal, $carla]);
        $this->get('/admin/students/'.$amina->id)->assertOk();
        $this->get('/admin/students/'.$bilal->id)->assertNotFound();

        $manager = $this->staff(Role::Administrator, 'Manager');
        $this->actingAs($manager);
        Livewire::test(ListStudents::class)->assertCanSeeTableRecords([$amina, $bilal, $carla]);
    }

    public function test_the_counsellor_records_a_status_what_the_student_wants_and_a_comment(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $amina = $this->student('Amina Khan', $counsellor);
        $this->actingAs($counsellor);

        Livewire::test(ViewStudent::class, ['record' => $amina->id])
            ->callAction('updateStatus', ['status' => 'contacted', 'wants' => 'A master in engineering in China', 'comment' => 'Called her; sending documents next week.'])
            ->assertHasNoActionErrors();
        Livewire::test(ViewStudent::class, ['record' => $amina->id])
            ->callAction('updateStatus', ['status' => 'documents', 'wants' => 'A master in engineering in China, CSC if possible', 'comment' => 'Passport received.']);

        $this->assertSame(2, $amina->followUps()->count());
        $current = $amina->fresh()->latestFollowUp;
        $this->assertSame('documents', $current->status);
        $this->assertSame($counsellor->id, $current->author_id);
        $this->assertSame('Collecting documents', $current->statusLabel());
        Livewire::test(ListStudents::class)->assertSee('Collecting documents')->assertSee('Passport received.')->assertSee('CSC if possible');
        Livewire::test(ViewStudent::class, ['record' => $amina->id])->callAction('updateStatus', ['status' => 'not-a-status'])->assertHasActionErrors();

        $this->assertTrue(Audit::where('action', 'student.follow_up')->exists());
        $this->assertStringNotContainsString('Passport received', json_encode(Audit::where('action', 'student.follow_up')->first()->after), 'notes are not copied into the audit trail');
    }

    public function test_the_counsellor_opens_the_documents_and_applications_of_their_student_only(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $amina = $this->student('Amina Khan', $counsellor);
        $bilal = $this->student('Bilal Shah');
        $document = $this->uploadPassport($amina);
        $theirs = $this->uploadPassport($bilal);
        $plan = app(PlanService::class)->create($amina, $this->publishedCycle());
        $otherPlan = app(PlanService::class)->create($bilal, $this->publishedCycle());

        $this->actingAs($counsellor);
        $this->get(route('files.download', $document))->assertOk(); // no sharing needed for an assigned student
        $this->get(route('review.download', $plan->public_id))->assertOk()->assertHeader('content-type', 'application/pdf'); // even a draft
        $this->get(route('review.preview', $plan->public_id))->assertOk();
        $this->assertTrue(Audit::where('action', 'document.downloaded')->where('actor_id', $counsellor->id)->exists(), 'every opening is audited');

        $this->get(route('files.download', $theirs))->assertForbidden();
        $this->get(route('review.download', $otherPlan->public_id))->assertForbidden();

        // A counsellor with no assignment, and a manager, cannot open the documents of a student they do not look after.
        $this->actingAs($this->staff(Role::Advisor, 'Counsellor Two'))->get(route('files.download', $document))->assertForbidden();
        $this->actingAs($this->staff(Role::Administrator, 'Manager'))->get(route('files.download', $document))->assertForbidden();
        Livewire::test(ViewStudent::class, ['record' => $amina->id])->assertSee('Update status');
    }

    public function test_the_counsellor_and_the_student_exchange_messages(): void
    {
        Notification::fake();
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $amina = $this->student('Amina Khan', $counsellor);
        Notification::assertSentTo($amina, CaseUpdateNotification::class); // told who their counsellor is

        $this->actingAs($amina)->get(route('student.counsellor'))->assertOk()->assertSee('Counsellor One')->assertSee('can read your details');
        $this->post(route('student.counsellor.store'), ['body' => 'I want to study engineering in China.'])->assertRedirect();
        $this->post(route('student.counsellor.store'), ['body' => ''])->assertSessionHasErrors('body');
        $this->assertTrue(Counselling::awaitingReply($amina));

        $this->actingAs($counsellor);
        Livewire::test(ListStudents::class)->assertSee('Amina Khan');
        Livewire::test(ViewStudent::class, ['record' => $amina->id])->assertSee('Update status');
        $this->assertSame(1, $amina->counsellorMessages()->count());

        app(Counselling::class)->message($amina, $counsellor, 'Welcome Amina, please upload your passport.');
        $this->assertFalse(Counselling::awaitingReply($amina->fresh()));
        $this->actingAs($amina)->get(route('student.counsellor'))->assertSee('Welcome Amina, please upload your passport.')->assertSee('I want to study engineering in China.');
        Notification::assertSentToTimes($amina, CaseUpdateNotification::class, 2);

        // Another student never sees this conversation.
        $this->actingAs($this->student('Bilal Shah'))->get(route('student.counsellor'))->assertOk()->assertDontSee('Welcome Amina');
    }

    public function test_only_managers_assign_counsellors_and_only_to_people_who_can_counsel(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $amina = $this->student('Amina Khan');
        $manager = $this->staff(Role::Administrator, 'Manager');
        $editor = User::factory()->create(); // a plain student cannot counsel

        $this->actingAs($manager);
        Livewire::test(ViewStudent::class, ['record' => $amina->id])->callAction('assign', ['counsellor_id' => $counsellor->id])->assertHasNoActionErrors();
        $this->assertSame($counsellor->id, $amina->fresh()->counsellor_id);
        $this->assertTrue($counsellor->fresh()->counsels($amina->fresh()));

        $this->expectException(\InvalidArgumentException::class);
        app(Counselling::class)->assign($amina, $editor);
    }

    public function test_a_counsellor_cannot_assign_and_non_counsellors_do_not_see_students(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $amina = $this->student('Amina Khan', $counsellor);
        $this->actingAs($counsellor);
        Livewire::test(ViewStudent::class, ['record' => $amina->id])->assertActionHidden('assign');

        $this->actingAs(User::factory()->role(Role::PartnerManager)->create())->get('/admin/students')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/students')->assertForbidden();
        $this->actingAs($amina)->get('/admin/students')->assertForbidden();
    }

    public function test_viewing_a_student_is_logged(): void
    {
        $counsellor = $this->staff(Role::Advisor, 'Counsellor One');
        $amina = $this->student('Amina Khan', $counsellor);
        $this->actingAs($counsellor)->get('/admin/students/'.$amina->id)->assertOk();
        $this->assertTrue(Audit::where('action', 'student.viewed')->where('actor_id', $counsellor->id)->where('auditable_id', $amina->id)->exists());
        $this->assertSame(0, StudentFollowUp::count());
    }
}
