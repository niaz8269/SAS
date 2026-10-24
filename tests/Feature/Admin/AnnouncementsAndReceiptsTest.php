<?php

namespace Tests\Feature\Admin;

use App\Domain\Admin\Announcements;
use App\Domain\Cases\CaseService;
use App\Domain\Communication\Preferences;
use App\Domain\Counselling\Counselling;
use App\Domain\Documents\InvoicePdf;
use App\Domain\StudentWorkspace\PlanService;
use App\Enums\Role;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Models\Announcement;
use App\Models\Invoice;
use App\Models\PartnerOrganisation;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Notifications\CaseUpdateNotification;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

/** Messages to groups of students, and invoice and receipt PDFs. */
class AnnouncementsAndReceiptsTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function admin(): User
    {
        return User::factory()->role(Role::Administrator)->create(['name' => 'Admin One']);
    }

    private function announcement(string $audience, ?string $value = null): Announcement
    {
        $a = new Announcement(['title' => 'Deadline moved', 'body' => 'The deadline changed. Please check your application.', 'audience' => $audience, 'audience_value' => $value]);
        $a->forceFill(['created_by' => $this->admin()->id])->save();

        return $a;
    }

    public function test_each_audience_reaches_exactly_the_right_students(): void
    {
        $admin = $this->admin();
        $counsellor = User::factory()->role(Role::Advisor)->create();
        $org = PartnerOrganisation::create(['name' => 'Star Agency', 'kind' => 'agent', 'contact_email' => 'a@example.test']);
        $cycle = $this->publishedCycle([], $this->exactDeadline('+5 days'));
        $far = $this->publishedCycle([], $this->exactDeadline('+90 days'));

        $applied = User::factory()->create(['name' => 'Applied']);
        $agentStudent = User::factory()->create(['name' => 'Via Agent', 'managed_by_organisation_id' => $org->id]);
        $mine = User::factory()->create(['name' => 'Mine']);
        $quiet = User::factory()->create(['name' => 'No Plan']);
        app(Counselling::class)->assign($mine, $counsellor);
        app(PlanService::class)->create($applied, $cycle);
        $farPlan = app(PlanService::class)->create(User::factory()->create(['name' => 'Far Fay']), $far);
        $offer = app(PlanService::class)->create(User::factory()->create(['name' => 'Offer Olga']), $this->publishedCycle());
        $offer->forceFill(['stage' => 'passed', 'journey_stage' => 'offer'])->save();
        $names = fn (Announcement $a) => app(Announcements::class)->recipients($a)->pluck('name')->sort()->values()->all();

        $this->assertContains('No Plan', $names($this->announcement('all')));
        $this->assertSame(['Applied'], $names($this->announcement('programme', (string) $cycle->programme_id)));
        $this->assertSame(['Via Agent'], $names($this->announcement('agent', (string) $org->id)));
        $this->assertSame(['Mine'], $names($this->announcement('counsellor', (string) $counsellor->id)));
        $this->assertSame(['Offer Olga'], $names($this->announcement('journey', 'offer')));
        $this->assertSame(['Applied'], $names($this->announcement('deadline', '14')), 'only the application closing within 14 days');
        $this->assertContains('Far Fay', $names($this->announcement('deadline', '120')));
        $this->assertNotContains('No Plan', $names($this->announcement('deadline', '120')));
        $this->assertNotNull($admin);
        $this->assertNotNull($farPlan);
        $this->assertNotNull($agentStudent);
        $this->assertNotNull($quiet);
    }

    public function test_an_announcement_is_checked_sent_once_and_cannot_be_changed_afterwards(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $student = User::factory()->create(['name' => 'Amina Khan']);
        $this->actingAs($admin);

        Livewire::test(ManageAnnouncements::class)->callAction('create', ['title' => 'Hello all', 'body' => 'A short message for everyone.', 'audience' => 'all'])->assertHasNoActionErrors();
        $a = Announcement::firstOrFail();
        $this->assertFalse($a->isSent());
        $this->assertSame($admin->id, $a->created_by);

        Livewire::test(ManageAnnouncements::class)->callTableAction('send', $a)->assertHasNoTableActionErrors();
        $a->refresh();
        $this->assertTrue($a->isSent());
        $this->assertGreaterThanOrEqual(1, $a->recipients);
        Notification::assertSentTo(Student::find($student->id), AnnouncementNotification::class); // the recipients are Student objects, which are the same rows as users

        $this->expectException(\InvalidArgumentException::class);
        app(Announcements::class)->send($a, $admin);
    }

    public function test_only_admins_see_the_announcements_screen_and_a_student_with_notices_off_is_skipped(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->role(Role::Advisor)->create())->get('/admin/announcements')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/announcements')->assertOk();

        $student = User::factory()->create(['name' => 'Quiet Quinn']);
        Preferences::ensureDefaults($student);
        foreach (['in_app', 'mail'] as $channel) {
            Preferences::set($student, 'transactional', $channel, false);
        }
        $a = $this->announcement('all');
        app(Announcements::class)->send($a, $this->admin());
        Notification::assertNotSentTo(Student::find($student->id), AnnouncementNotification::class);
    }

    public function test_invoices_and_receipts_are_pdfs_for_their_owner_and_staff_only(): void
    {
        Notification::fake();
        $student = User::factory()->create(['name' => 'Amina Khan']);
        $case = app(CaseService::class)->open($student, null, null, ['title' => 'Chevening case']);
        $invoice = Invoice::where('case_id', $case->id)->firstOrFail();
        $pdf = app(InvoicePdf::class);

        $this->assertSame('SAS-INV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT), $pdf->number($invoice));
        $this->assertStringStartsWith('%PDF', $pdf->render($invoice));

        $this->actingAs($student)->get(route('invoices.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf')->assertHeader('content-disposition', 'attachment; filename="'.$pdf->filename($invoice).'"');
        $this->get(route('student.cases.show', $case))->assertSee('Download invoice (PDF)');
        $this->actingAs(User::factory()->create())->get(route('invoices.pdf', $invoice))->assertForbidden();
        $this->actingAs($this->admin())->get(route('invoices.pdf', $invoice))->assertOk();
        $this->actingAs(User::factory()->role(Role::Advisor)->create())->get(route('invoices.pdf', $invoice))->assertForbidden(); // not their case

        app(CaseService::class)->markPaid($invoice, null, 'BANK-123', $this->admin());
        $invoice->refresh();
        $this->assertSame('SAS-RCT-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT), $pdf->number($invoice));
        $this->actingAs($student)->get(route('student.cases.show', $case))->assertSee('Download receipt (PDF)');
        Notification::assertSentTo($student, CaseUpdateNotification::class, fn ($n) => $n->receipt?->is($invoice));

        $voided = Invoice::where('case_id', $case->id)->where('kind', '!=', 'application_fee')->first();
        $this->assertNull($voided);
    }
}
