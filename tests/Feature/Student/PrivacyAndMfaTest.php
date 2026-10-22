<?php

namespace Tests\Feature\Student;

use App\Domain\Identity\AccountPrivacy;
use App\Domain\Identity\TwoFactor;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\EnquiryMessage;
use App\Models\User;
use App\Notifications\ConfirmAccountDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class PrivacyAndMfaTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents']);
    }

    private function populated(): User
    {
        $user = User::factory()->create(['email' => 'amina@example.test']);
        $this->actingAs($user)->post('/student/documents', ['file' => UploadedFile::fake()->createWithContent('t.pdf', self::PDF), 'type' => 'transcript', 'acknowledge' => 1]);
        $this->post('/student/applications', ['cycle_id' => $this->publishedCycle()->id]);
        $user->studentProfile()->create(['institution' => 'Example Uni']);
        $e = new Enquiry(['category' => 'advisory', 'name' => 'Amina Khan', 'email' => 'amina@example.test', 'phone' => '+441234567', 'message' => 'My private question']);
        $e->user_id = $user->id;
        $e->save();
        EnquiryMessage::create(['enquiry_id' => $e->id, 'visibility' => 'internal', 'body' => 'INTERNAL NOTE']);
        EnquiryMessage::create(['enquiry_id' => $e->id, 'visibility' => 'public', 'body' => 'Public reply']);

        return $user;
    }

    // ---------------------------------------------------------------- export

    public function test_export_needs_the_password_and_contains_the_users_data_but_no_internal_notes_or_secrets(): void
    {
        $user = $this->populated();
        $this->post('/student/privacy/export', ['password' => 'wrong'])->assertSessionHasErrors('password');

        $r = $this->post('/student/privacy/export', ['password' => 'password'])->assertOk();
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $body = $r->getContent();
        $json = json_decode($body, true);
        $this->assertSame('amina@example.test', $json['account']['email']);
        $this->assertSame('Example Uni', $json['profile']['institution']);
        $this->assertCount(1, $json['documents']);
        $this->assertCount(1, $json['application_plans']);
        $this->assertSame('My private question', $json['enquiries'][0]['message']);
        $this->assertStringNotContainsString('INTERNAL NOTE', $body);
        $this->assertStringNotContainsString('storage_key', $body);
        $this->assertStringNotContainsString('password', $body);
        $this->assertDatabaseHas('audits', ['action' => 'account.exported', 'actor_id' => $user->id]);
    }

    public function test_one_student_cannot_export_another_students_data(): void
    {
        $this->populated();
        $other = User::factory()->create();
        $json = json_decode($this->actingAs($other)->post('/student/privacy/export', ['password' => 'password'])->getContent(), true);
        $this->assertSame($other->email, $json['account']['email']);
        $this->assertSame([], $json['documents']);
        $this->assertSame([], $json['enquiries']);
    }

    // --------------------------------------------------------------- deletion

    public function test_deletion_needs_password_then_an_emailed_single_use_link_and_typed_confirmation(): void
    {
        Notification::fake();
        $user = $this->populated();

        $this->post('/student/privacy/delete-request', ['password' => 'wrong'])->assertSessionHasErrors('password');
        Notification::assertNothingSent();

        $this->post('/student/privacy/delete-request', ['password' => 'password'])->assertSessionHas('status');
        $url = null;
        Notification::assertSentTo($user, ConfirmAccountDeletion::class, function ($n) use ($user, &$url) {
            $url = (new \ReflectionProperty($n, 'url'))->getValue($n);

            return str_contains($n->toMail($user)->actionUrl, '/student/privacy/delete/');
        });
        $this->assertNotNull(User::find($user->id), 'requesting must not delete anything');

        $token = basename($url);
        $this->delete('/student/privacy/delete/'.$token, ['password' => 'password', 'confirmation' => 'nope'])->assertSessionHasErrors('confirmation');
        $this->delete('/student/privacy/delete/'.$token, ['password' => 'wrong', 'confirmation' => 'DELETE'])->assertSessionHasErrors('password');
        $this->assertNotNull(User::find($user->id));

        $this->delete('/student/privacy/delete/'.$token, ['password' => 'password', 'confirmation' => 'DELETE'])->assertRedirect(route('home'));
        $this->assertNull(User::find($user->id));
        $this->assertGuest();
    }

    public function test_the_deletion_link_is_bound_to_the_user_expires_and_cannot_be_reused(): void
    {
        Notification::fake();
        $user = $this->populated();
        $this->post('/student/privacy/delete-request', ['password' => 'password']);
        $token = null;
        Notification::assertSentTo($user, ConfirmAccountDeletion::class, function ($n) use (&$token) {
            $token = basename((new \ReflectionProperty($n, 'url'))->getValue($n));

            return true;
        });

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get('/student/privacy/delete/'.$token)->assertNotFound();
        $this->actingAs($stranger)->delete('/student/privacy/delete/'.$token, ['password' => 'password', 'confirmation' => 'DELETE'])->assertNotFound();

        $this->actingAs($user);
        $this->travel(25)->hours();
        $this->get('/student/privacy/delete/'.$token)->assertNotFound(); // expired
        $this->assertNotNull(User::find($user->id));
    }

    public function test_a_new_request_cancels_the_previous_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user);
        $tokens = [];
        foreach ([1, 2] as $i) {
            $this->post('/student/privacy/delete-request', ['password' => 'password']);
        }
        Notification::assertSentToTimes($user, ConfirmAccountDeletion::class, 2);
        $this->assertSame(1, DB::table('deletion_requests')->where('status', 'requested')->count());
        $this->assertSame(1, DB::table('deletion_requests')->where('status', 'cancelled')->count());
    }

    public function test_erasure_removes_documents_files_and_personal_data_but_keeps_anonymised_history(): void
    {
        $user = $this->populated();
        $version = Document::firstOrFail()->currentVersion;
        Storage::disk('private_documents')->assertExists('released/'.$version->storage_key);
        $enquiryId = Enquiry::firstOrFail()->id;

        $files = app(AccountPrivacy::class)->erase($user);

        $this->assertSame(1, $files);
        Storage::disk('private_documents')->assertMissing('released/'.$version->storage_key);
        foreach (['users', 'student_profiles', 'documents', 'document_versions', 'application_plans', 'application_tasks', 'notification_events', 'saved_scholarships'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} should be empty");
        }
        $e = Enquiry::find($enquiryId);
        $this->assertSame('Deleted user', $e->name);
        $this->assertStringNotContainsString('amina', $e->email);
        $this->assertNull($e->phone);
        $this->assertStringNotContainsString('private question', $e->message);
        $this->assertSame(0, $e->messages()->count());
        $this->assertDatabaseHas('deletion_tombstones', ['email_hash' => app(AccountPrivacy::class)->emailHash('AMINA@example.test ')]); // case/space-insensitive
        $this->assertSame(0, DB::table('audits')->where('after', 'like', '%amina%')->count());
    }

    public function test_the_tombstone_holds_no_readable_email(): void
    {
        $user = $this->populated();
        app(AccountPrivacy::class)->erase($user);
        $this->assertSame(0, DB::table('deletion_tombstones')->where('email_hash', 'like', '%amina%')->count());
        $this->assertSame(64, strlen(DB::table('deletion_tombstones')->value('email_hash')));
    }

    public function test_staff_accounts_cannot_self_delete(): void
    {
        $staff = User::factory()->role(Role::Editor)->create();
        $this->actingAs($staff)->post('/student/privacy/delete-request', ['password' => 'password'])->assertSessionHas('error');
        $this->expectException(\DomainException::class);
        app(AccountPrivacy::class)->erase($staff);
    }

    public function test_restoring_a_backup_and_replaying_tombstones_re_erases_the_deleted_user(): void
    {
        $user = $this->populated();
        $email = $user->email;
        $privacy = app(AccountPrivacy::class);

        // Snapshot = "backup" taken before deletion.
        $backup = ['users' => DB::table('users')->get(), 'enquiries' => DB::table('enquiries')->get()];
        $tomb = null;
        $privacy->erase($user);
        $tomb = DB::table('deletion_tombstones')->get()->all();

        // Simulate the restore: the user and their enquiry come back, the tombstone table is the (newer) retained record.
        DB::table('deletion_tombstones')->delete();
        DB::table('enquiries')->delete(); // the restore replaces the table with the older copy
        foreach ($backup['users'] as $row) {
            DB::table('users')->insert((array) $row);
        }
        foreach ($backup['enquiries'] as $row) {
            DB::table('enquiries')->insert((array) $row);
        }
        foreach ($tomb as $t) {
            DB::table('deletion_tombstones')->insert((array) $t);
        }
        $this->assertSame($email, User::firstOrFail()->email); // data is back

        $this->artisan('sas:replay-deletions')->expectsOutput('Erased again: 1 account(s).')->assertSuccessful();

        $this->assertSame(0, User::count());
        $this->assertSame('Deleted user', Enquiry::firstOrFail()->name);
        $this->assertNotNull(DB::table('deletion_tombstones')->value('replayed_at'));
        $this->artisan('sas:replay-deletions')->expectsOutput('Erased again: 0 account(s).'); // idempotent
    }

    // -------------------------------------------------------------------- MFA

    private function enable(User $user): array
    {
        $tf = app(TwoFactor::class);
        $secret = $tf->newSecret();
        $codes = $tf->enable($user, $secret);

        return [$secret, $codes];
    }

    private function code(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_a_student_can_turn_on_two_step_verification_with_a_valid_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/student/security')->assertOk()->assertSee('Turn on two-step verification')->assertSee('<img src="data:image/svg+xml;base64,', false);
        $secret = session('mfa_setup_secret');

        $this->post('/student/security/enable', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse(app(TwoFactor::class)->enabled($user->fresh()));

        $this->post('/student/security/enable', ['code' => $this->code($secret)])->assertRedirect(route('student.security'));
        $user->refresh();
        $this->assertTrue(app(TwoFactor::class)->enabled($user));
        $this->assertCount(8, $user->app_authentication_recovery_codes);
        $this->get('/student/security')->assertSee('Save your recovery codes');
        $this->get('/student/security')->assertDontSee('Save your recovery codes'); // shown once only
    }

    public function test_login_with_an_authenticator_requires_the_code_before_any_session_exists(): void
    {
        $user = User::factory()->create(['email' => 'm@example.test']);
        [$secret] = $this->enable($user);

        $this->post('/login', ['email' => 'm@example.test', 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get('/student')->assertRedirect('/login');

        $this->post('/two-factor-challenge', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $this->code($secret)])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_codes_work_once(): void
    {
        $user = User::factory()->create(['email' => 'r@example.test']);
        [, $codes] = $this->enable($user);

        $this->post('/login', ['email' => 'r@example.test', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => strtoupper($codes[0])])->assertRedirect(route('student.dashboard'));
        $this->post('/logout');

        $this->post('/login', ['email' => 'r@example.test', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $codes[0]])->assertSessionHasErrors('code'); // already used
        $this->assertGuest();
        $this->assertCount(7, $user->fresh()->app_authentication_recovery_codes);
    }

    public function test_a_wrong_password_never_reaches_the_challenge_and_the_challenge_needs_a_prior_password_step(): void
    {
        $user = User::factory()->create(['email' => 'w@example.test']);
        $this->enable($user);
        $this->post('/login', ['email' => 'w@example.test', 'password' => 'bad'])->assertSessionHasErrors('email');
        $this->get('/two-factor-challenge')->assertRedirect('/login');
        $this->post('/two-factor-challenge', ['code' => '123456'])->assertRedirect('/login');
    }

    public function test_the_password_step_expires_after_ten_minutes(): void
    {
        $user = User::factory()->create(['email' => 'e@example.test']);
        [$secret] = $this->enable($user);
        $this->post('/login', ['email' => 'e@example.test', 'password' => 'password']);
        $this->travel(11)->minutes();
        $this->post('/two-factor-challenge', ['code' => $this->code($secret)])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_staff_with_an_authenticator_cannot_bypass_it_through_the_site_login(): void
    {
        $staff = User::factory()->role(Role::Editor)->create(['email' => 's@example.test']);
        [$secret] = $this->enable($staff);
        $this->post('/login', ['email' => 's@example.test', 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['code' => $this->code($secret)])->assertRedirect('/admin');
    }

    public function test_challenge_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create(['email' => 'l@example.test']);
        $this->enable($user);
        $this->post('/login', ['email' => 'l@example.test', 'password' => 'password']);
        $statuses = collect(range(1, 40))->map(fn () => $this->post('/two-factor-challenge', ['code' => '111111'])->getStatusCode());
        $this->assertTrue($statuses->contains(429));
    }

    public function test_turning_off_needs_the_password_and_staff_cannot_turn_it_off(): void
    {
        $user = User::factory()->create();
        $this->enable($user);
        $this->actingAs($user)->post('/student/security/disable', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertTrue(app(TwoFactor::class)->enabled($user->fresh()));
        $this->post('/student/security/disable', ['password' => 'password']);
        $this->assertFalse(app(TwoFactor::class)->enabled($user->fresh()));

        $staff = User::factory()->role(Role::Editor)->create();
        $this->enable($staff);
        $this->actingAs($staff)->post('/student/security/disable', ['password' => 'password'])->assertSessionHas('error');
        $this->assertTrue(app(TwoFactor::class)->enabled($staff->fresh()));
    }

    public function test_regenerating_recovery_codes_invalidates_the_old_ones(): void
    {
        $user = User::factory()->create();
        [, $old] = $this->enable($user);
        $this->actingAs($user)->post('/student/security/recovery-codes', ['password' => 'password'])->assertRedirect();
        $new = $user->fresh()->app_authentication_recovery_codes;
        $this->assertNotEquals($old, $new);
        $this->assertFalse(app(TwoFactor::class)->challenge($user->fresh(), $old[0]));
    }
}
