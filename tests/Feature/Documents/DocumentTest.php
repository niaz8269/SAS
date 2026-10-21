<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\DocumentService;
use App\Enums\ScanStatus;
use App\Jobs\ScanDocumentJob;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents']);
    }

    private function pdf(string $name = 'transcript.pdf', string $body = self::PDF): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $body);
    }

    private function upload(User $user, UploadedFile $file, array $over = [])
    {
        return $this->actingAs($user)->post('/student/documents', array_merge(['file' => $file, 'type' => 'transcript', 'acknowledge' => '1'], $over));
    }

    public function test_clean_upload_goes_through_quarantine_then_becomes_available(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->upload($user, $this->pdf())->assertRedirect();

        $version = DocumentVersion::firstOrFail();
        $this->assertSame(ScanStatus::Pending, $version->scan_status);
        Storage::disk('private_documents')->assertExists('quarantine/'.$version->storage_key);
        Storage::disk('private_documents')->assertMissing('released/'.$version->storage_key);
        Queue::assertPushed(ScanDocumentJob::class);

        (new ScanDocumentJob($version->id))->handle(app(DocumentService::class), app(\App\Domain\Documents\Scanner::class));
        $version->refresh();
        $this->assertSame(ScanStatus::Available, $version->scan_status);
        Storage::disk('private_documents')->assertExists('released/'.$version->storage_key);
        Storage::disk('private_documents')->assertMissing('quarantine/'.$version->storage_key);
    }

    public function test_a_pending_scan_cannot_be_downloaded(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->upload($user, $this->pdf());
        $doc = Document::firstOrFail();
        $this->actingAs($user)->get(route('student.documents.download', $doc))->assertNotFound();
    }

    public function test_available_documents_download_privately_for_the_owner_only_and_are_logged(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->upload($owner, $this->pdf()); // sync queue + fake scanner => available
        $doc = Document::firstOrFail();
        $this->assertSame(ScanStatus::Available, $doc->currentVersion->scan_status);

        $r = $this->actingAs($owner)->get(route('student.documents.download', $doc))->assertOk();
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        $this->assertDatabaseHas('audits', ['action' => 'document.downloaded', 'actor_id' => $owner->id]);

        $this->actingAs($other)->get(route('student.documents.download', $doc))->assertForbidden();
        $this->get('/student/documents/'.$doc->id.'/download')->assertNotFound(); // internal ids are not routable
        auth()->logout();
        $this->get(route('student.documents.download', $doc))->assertRedirect('/login');
    }

    public function test_storage_keys_are_opaque_and_never_leak_the_original_name_or_user(): void
    {
        $user = User::factory()->create();
        $this->upload($user, $this->pdf('Passport Amina Khan.pdf'));
        $v = DocumentVersion::firstOrFail();
        $this->assertDoesNotMatchRegularExpression('/passport|amina|khan/i', $v->storage_key);
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $v->storage_key);
    }

    public function test_eicar_infected_file_is_rejected_and_removed(): void
    {
        $user = User::factory()->create();
        $this->upload($user, $this->pdf('bad.pdf', self::PDF.' EICAR-STANDARD-ANTIVIRUS-TEST-FILE'));
        $v = DocumentVersion::firstOrFail();
        $this->assertSame(ScanStatus::Rejected, $v->scan_status);
        Storage::disk('private_documents')->assertMissing('quarantine/'.$v->storage_key);
        Storage::disk('private_documents')->assertMissing('released/'.$v->storage_key);
        $this->actingAs($user)->get(route('student.documents.download', $v->document))->assertNotFound();
    }

    public function test_scanner_outage_keeps_the_file_pending_and_never_released(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->upload($user, $this->pdf());
        $v = DocumentVersion::firstOrFail();

        $down = new class implements \App\Domain\Documents\Scanner
        {
            public function scan($stream): array
            {
                throw new \RuntimeException('ClamAV unreachable');
            }
        };
        try {
            (new ScanDocumentJob($v->id))->handle(app(DocumentService::class), $down);
            $this->fail('should rethrow so the queue retries');
        } catch (\RuntimeException) {
        }
        $this->assertSame(ScanStatus::Pending, $v->fresh()->scan_status);
        Storage::disk('private_documents')->assertExists('quarantine/'.$v->storage_key);
    }

    public function test_dangerous_and_mismatched_files_are_refused(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // 11 rapid attempts below
        $user = User::factory()->create();
        $cases = [
            'executable' => $this->pdf('setup.exe', 'MZ binary'),
            'php script' => $this->pdf('shell.php', '<?php system($_GET[0]);'),
            'archive' => $this->pdf('files.zip', "PK\x03\x04"),
            'double extension php' => $this->pdf('cv.php.pdf', self::PDF),
            'double extension exe' => $this->pdf('scan.exe.png', "\x89PNG\r\n\x1a\n"),
            'html' => $this->pdf('page.html', '<script>1</script>'),
            'svg' => $this->pdf('x.svg', '<svg onload=alert(1)>'),
            'pdf extension but html content' => $this->pdf('fake.pdf', '<html><script>alert(1)</script></html>'),
            'png extension but pdf content' => $this->pdf('fake.png', self::PDF),
            'docx extension but plain zip' => $this->pdf('fake.docx', "PK\x03\x04junk"),
            'no extension' => $this->pdf('README', self::PDF),
        ];
        foreach ($cases as $label => $file) {
            $this->upload($user, $file)->assertSessionHasErrors('file');
            $this->assertSame(0, DocumentVersion::count(), "{$label} must not be stored");
        }
    }

    public function test_uploads_are_rate_limited(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 10) as $i) {
            $this->upload($user, $this->pdf("a{$i}.pdf"))->assertRedirect();
        }
        $this->upload($user, $this->pdf('one-too-many.pdf'))->assertStatus(429);
    }

    public function test_oversize_files_are_refused(): void
    {
        config(['sas.uploads.max_kb' => 1]);
        $this->upload(User::factory()->create(), $this->pdf('big.pdf', self::PDF.str_repeat('a', 3000)))->assertSessionHasErrors('file');
        $this->assertSame(0, Document::count());
    }

    public function test_storage_quota_is_enforced(): void
    {
        config(['sas.uploads.quota_mb' => 0]);
        $this->upload(User::factory()->create(), $this->pdf())->assertSessionHasErrors('file');
    }

    public function test_acknowledgement_is_required_before_a_sensitive_upload(): void
    {
        $this->upload(User::factory()->create(), $this->pdf(), ['acknowledge' => null])->assertSessionHasErrors('acknowledge');
    }

    public function test_valid_docx_png_and_jpeg_are_accepted(): void
    {
        $user = User::factory()->create();
        $docx = tempnam(sys_get_temp_dir(), 'dx');
        $zip = new \ZipArchive;
        $zip->open($docx, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('word/document.xml', '<w:document/>');
        $zip->close();

        $this->upload($user, new UploadedFile($docx, 'cv.docx', null, null, true))->assertSessionHasNoErrors();
        $this->upload($user, $this->pdf('scan.png', "\x89PNG\r\n\x1a\n".str_repeat('x', 20)))->assertSessionHasNoErrors();
        $this->upload($user, $this->pdf('photo.JPG', "\xFF\xD8\xFF\xE0".str_repeat('x', 20)))->assertSessionHasNoErrors();
        $this->assertSame(3, Document::count());
    }

    public function test_new_versions_keep_history_and_deleting_removes_files(): void
    {
        $user = User::factory()->create();
        $this->upload($user, $this->pdf());
        $doc = Document::firstOrFail();
        $this->actingAs($user)->post(route('student.documents.versions.store', $doc), ['file' => $this->pdf('v2.pdf')])->assertRedirect();
        $doc->refresh();
        $this->assertSame(2, $doc->versions()->count());
        $this->assertSame(2, $doc->currentVersion->version);
        $keys = $doc->versions->pluck('storage_key');

        $this->actingAs($user)->delete(route('student.documents.destroy', $doc))->assertRedirect();
        $this->assertSame(0, Document::count());
        foreach ($keys as $key) {
            Storage::disk('private_documents')->assertMissing('released/'.$key);
        }
    }

    public function test_deleting_a_linked_document_requires_confirmation_of_the_references(): void
    {
        $user = User::factory()->create();
        $this->upload($user, $this->pdf());
        $doc = Document::firstOrFail();
        $cycle = \App\Models\ScholarshipCycle::factory()->published()->create();
        $this->actingAs($user)->post('/student/applications', ['cycle_id' => $cycle->id]);
        $plan = \App\Models\ApplicationPlan::firstOrFail();
        $this->post(route('student.applications.documents.attach', $plan), ['document' => $doc->public_id])->assertRedirect();

        $this->delete(route('student.documents.destroy', $doc))->assertSessionHas('error');
        $this->assertSame(1, Document::count());
        $this->delete(route('student.documents.destroy', $doc), ['confirm' => 1]);
        $this->assertSame(0, Document::count());
    }

    public function test_a_student_cannot_link_another_students_document_to_their_plan(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->upload($a, $this->pdf());
        $doc = Document::firstOrFail();
        $cycle = \App\Models\ScholarshipCycle::factory()->published()->create();
        $this->actingAs($b)->post('/student/applications', ['cycle_id' => $cycle->id]);
        $plan = \App\Models\ApplicationPlan::where('user_id', $b->id)->firstOrFail();
        $this->post(route('student.applications.documents.attach', $plan), ['document' => $doc->public_id])->assertForbidden();
        $this->assertDatabaseCount('document_associations', 0);
    }

    public function test_private_disk_has_no_public_url_or_local_serving(): void
    {
        $config = config('filesystems.disks.private_documents');
        $this->assertSame('private', $config['visibility']);
        $this->assertArrayNotHasKey('url', $config);
        $this->assertFalse($config['serve'] ?? false);
    }
}
