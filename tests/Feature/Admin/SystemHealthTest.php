<?php

namespace Tests\Feature\Admin;

use App\Domain\Counselling\Counselling;
use App\Domain\Editorial\AuditLog;
use App\Domain\System\Backup;
use App\Domain\System\Health;
use App\Enums\Role;
use App\Filament\Pages\SystemHealth;
use App\Filament\Resources\Audits\Pages\ManageAudits;
use App\Models\Audit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/** System health, backups and the staff activity view. */
class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents']);
        $this->withoutMiddleware(ThrottleRequests::class);
        File::deleteDirectory(storage_path('app/backups'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/backups'));
        parent::tearDown();
    }

    public function test_a_backup_holds_the_database_and_the_documents_and_old_ones_are_pruned(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->post('/student/documents', ['file' => UploadedFile::fake()->createWithContent('passport.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF"), 'type' => 'passport', 'acknowledge' => '1']);

        $result = app(Backup::class)->run();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($result['path']));
        $this->assertNotFalse($zip->locateName('database.sqlite') ?: $zip->locateName('database.json'), 'the database is in the backup');
        $this->assertSame(1, $result['files']);
        $docs = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->filter(fn ($n) => str_starts_with($n, 'documents/'));
        $this->assertCount(1, $docs, 'the uploaded document is in the backup');
        $zip->close();
        $this->assertTrue(Audit::where('action', 'system.backup')->exists());

        File::ensureDirectoryExists(app(Backup::class)->directory());
        foreach (range(1, 20) as $i) {
            touch(app(Backup::class)->directory().'/sas-backup-2020010'.($i % 10).'-0000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'.zip', now()->subDays(30 + $i)->timestamp);
        }
        app(Backup::class)->run();
        $this->assertCount(Backup::KEEP, app(Backup::class)->list());
        $this->artisan('sas:backup')->assertSuccessful();
    }

    public function test_health_checks_report_the_scheduler_backup_and_failures(): void
    {
        $byLabel = fn () => collect(app(Health::class)->checks())->keyBy('label');
        $this->assertSame('never ran', $byLabel()['Scheduler']['value']);
        $this->artisan('sas:heartbeat')->assertSuccessful();
        $this->assertSame('running', $byLabel()['Scheduler']['value']);
        $this->assertSame('ok', $byLabel()['Scheduler']['level']);
        $this->assertSame('none yet', $byLabel()['Latest backup']['value']);
        app(Backup::class)->run();
        $this->assertSame('ok', $byLabel()['Latest backup']['level']);
        $this->assertSame('0', $byLabel()['Failed background jobs']['value']);
        config(['sas.two_step.enabled' => true]);
        $this->assertSame('on', $byLabel()['Two-step sign-in']['value']);
    }

    public function test_only_the_super_admin_sees_system_health_and_can_back_up(): void
    {
        $super = User::factory()->role(Role::SuperAdmin)->create();
        $this->actingAs(User::factory()->role(Role::Administrator)->create())->get('/admin/system-health')->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Advisor)->create())->get('/admin/system-health')->assertForbidden();
        $this->actingAs($super)->get('/admin/system-health')->assertOk()->assertSee('Scheduler')->assertSee('Latest backup');

        Livewire::test(SystemHealth::class)->callAction('backup')->assertHasNoActionErrors();
        $this->assertCount(1, app(Backup::class)->list());
    }

    public function test_staff_activity_filters_by_person_topic_and_student(): void
    {
        $admin = User::factory()->role(Role::Administrator)->create(['name' => 'Admin One']);
        $counsellor = User::factory()->role(Role::Advisor)->create(['name' => 'Counsellor One']);
        $amina = User::factory()->create(['name' => 'Amina Khan']);
        $bilal = User::factory()->create(['name' => 'Bilal Shah']);
        app(Counselling::class)->assign($amina, $counsellor, $admin);
        AuditLog::record('student.viewed', $amina, null, null, null, $counsellor);
        AuditLog::record('student.viewed', $bilal, null, null, null, $admin);
        AuditLog::record('invoice.paid', null, null, ['amount_cents' => 100], null, $admin);

        $this->actingAs($admin);
        $names = fn ($t) => $t->instance()->getTableRecords()->pluck('action')->all();

        $byPerson = Livewire::test(ManageAudits::class)->filterTable('actor_id', $counsellor->id);
        $this->assertSame(['student.viewed'], $names($byPerson));
        $byTopic = Livewire::test(ManageAudits::class)->filterTable('topic', 'student_files');
        $this->assertSame(['student.viewed', 'student.viewed'], $names($byTopic));
        $money = Livewire::test(ManageAudits::class)->filterTable('topic', 'money');
        $this->assertSame(['invoice.paid'], $names($money));
        $aboutAmina = Livewire::test(ManageAudits::class)->filterTable('about_student', ['name' => 'Amina']);
        $this->assertEqualsCanonicalizing(['student.viewed', 'student.counsellor_assigned'], $names($aboutAmina));
        $this->get('/admin/audits')->assertOk()->assertSee('Staff activity');
    }
}
