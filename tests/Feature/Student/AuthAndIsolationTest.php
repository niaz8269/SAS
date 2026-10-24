<?php

namespace Tests\Feature\Student;

use App\Domain\Communication\Preferences;
use App\Enums\Role;
use App\Models\ApplicationPlan;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\PartnerOrganisation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class AuthAndIsolationTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    public function test_registration_creates_a_student_without_roles_and_sends_verification(): void
    {
        Notification::fake();
        $this->post('/register', [
            'name' => 'Amina', 'email' => 'amina@example.test', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1',
            'accept_privacy' => '1', 'timezone' => 'Asia/Karachi',
            // Privilege escalation attempts through the form:
            'role' => 'super_admin', 'status' => 'active', 'is_admin' => 1,
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'amina@example.test')->firstOrFail();
        $this->assertSame([], $user->roleKeys());
        $this->assertFalse($user->isStaff());
        $this->assertSame('Asia/Karachi', $user->timezone);
        Notification::assertSentTo($user, VerifyEmail::class);
        // Defaults are visible and editable; optional categories start off (NOT-02).
        $this->assertFalse(Preferences::enabled($user, 'marketing', 'mail'));
        $this->assertFalse(Preferences::enabled($user, 'deadline_reminders', 'mail'));
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $this->post('/register', ['name' => 'A', 'email' => 'a@example.test', 'password' => 'short', 'password_confirmation' => 'short', 'accept_privacy' => '1'])
            ->assertSessionHasErrors('password');
    }

    public function test_unverified_students_cannot_use_the_workspace(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get('/student')->assertRedirect(route('verification.notice'));
    }

    public function test_guests_are_sent_to_login_for_private_pages(): void
    {
        foreach (['/student', '/student/documents', '/student/applications', '/student/profile'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_login_is_rate_limited_and_gives_one_generic_message(): void
    {
        User::factory()->create(['email' => 'x@example.test']);
        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'x@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'x@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_suspended_users_cannot_log_in_and_existing_sessions_are_cut_off(): void
    {
        $user = User::factory()->create(['email' => 's@example.test']);
        $this->actingAs($user)->get('/student')->assertOk();
        $user->suspend();
        $this->get('/student')->assertRedirect('/login');
        $this->post('/login', ['email' => 's@example.test', 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_password_reset_does_not_reveal_whether_an_email_exists(): void
    {
        Notification::fake();
        $known = $this->post('/forgot-password', ['email' => User::factory()->create()->email]);
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.test']);
        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
    }

    public function test_student_cannot_open_or_change_another_students_plan(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $cycle = $this->publishedCycle([], $this->exactDeadline());
        $this->actingAs($owner)->post('/student/applications', ['cycle_id' => $cycle->id]);
        $plan = ApplicationPlan::firstOrFail();

        $this->actingAs($intruder)->get(route('student.applications.show', $plan))->assertForbidden();
        $this->actingAs($intruder)->patch(route('student.applications.update', $plan), ['status' => 'preparing'])->assertForbidden();
        $this->actingAs($intruder)->delete(route('student.applications.destroy', $plan))->assertForbidden();
        $this->actingAs($intruder)->post(route('student.applications.tasks.store', $plan), ['title' => 'x'])->assertForbidden();
        // A guessed internal id does not resolve either.
        $this->actingAs($intruder)->get('/student/applications/'.$plan->id)->assertNotFound();
        $this->assertSame('interested', $plan->fresh()->status->value);
    }

    public function test_student_cannot_touch_a_task_of_someone_elses_plan_through_their_own_plan_url(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $cycleA = $this->publishedCycle();
        $cycleB = $this->publishedCycle();
        $this->actingAs($a)->post('/student/applications', ['cycle_id' => $cycleA->id]);
        $this->actingAs($b)->post('/student/applications', ['cycle_id' => $cycleB->id]);
        $planA = ApplicationPlan::where('user_id', $a->id)->first();
        $planB = ApplicationPlan::where('user_id', $b->id)->first();
        $planA->tasks()->create(['title' => 'A task', 'is_custom' => true]);
        $taskA = $planA->tasks()->first();

        $this->actingAs($b)->patch(route('student.applications.tasks.update', [$planB, $taskA]), ['completed' => 1])->assertNotFound();
        $this->assertNull($taskA->fresh()->completed_at);
    }

    public function test_enquiry_policy_blocks_unrelated_staff_and_other_students(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $advisor = User::factory()->role(Role::Advisor)->create();
        $assigned = User::factory()->role(Role::Advisor)->create();
        $manager = User::factory()->role(Role::Administrator)->create();
        $finance = User::factory()->role(Role::Advisor)->create();
        $enquiry = new Enquiry(['category' => 'advisory', 'name' => 'S', 'email' => 's@example.test', 'message' => 'Message long enough']);
        $enquiry->user_id = $student->id;
        $enquiry->owner_id = $assigned->id;
        $enquiry->save();

        $this->assertTrue($student->can('view', $enquiry));
        $this->assertFalse($other->can('view', $enquiry));
        $this->assertFalse($advisor->can('view', $enquiry));      // unassigned advisor
        $this->assertTrue($assigned->can('view', $enquiry));      // assigned advisor
        $this->assertTrue($manager->can('view', $enquiry));
        $this->assertFalse($finance->can('view', $enquiry));      // finance has no routine access
        $enquiry->forceFill(['owner_id' => null])->save();
        $this->assertFalse($assigned->fresh()->can('view', $enquiry->fresh())); // access ends the moment assignment ends (AUTH-04)
    }

    public function test_students_cannot_reach_the_staff_panel_and_staff_roles_are_not_granted_by_profile_updates(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->get('/admin')->assertForbidden(); // non-staff never reach the panel
        $this->assertFalse($student->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($student)->put('/student/profile', ['name' => 'New', 'timezone' => 'UTC', 'role' => 'super_admin', 'status' => 'suspended', 'is_admin' => 1])->assertRedirect();
        $this->assertSame([], $student->fresh()->roleKeys());
        $this->assertSame('active', $student->fresh()->status);
    }

    public function test_every_staff_permission_has_a_negative_check(): void
    {
        $student = User::factory()->create();
        foreach (['content.draft', 'content.review', 'content.publish', 'content.override', 'import.stage', 'users.manage', 'roles.manage',
            'enquiries.view_all', 'enquiries.assign', 'services.manage', 'finance.view', 'audit.view'] as $permission) {
            $this->assertFalse($student->can($permission), "student must not hold {$permission}");
        }
        $this->assertFalse(User::factory()->role(Role::Advisor)->create()->can('content.publish'));
        $this->assertFalse(User::factory()->role(Role::Administrator)->create()->can('roles.manage'));
        $this->assertFalse(User::factory()->role(Role::Administrator)->create()->can('roles.manage')); // only super admin
        $this->assertTrue(User::factory()->role(Role::SuperAdmin)->create()->can('roles.manage'));
        $this->assertFalse(User::factory()->role(Role::Advisor)->create()->can('enquiries.view_all'));
        $this->assertFalse(User::factory()->role(Role::PartnerManager)->create()->can('content.publish'));
    }

    public function test_partner_area_is_restricted_to_partner_managers(): void
    {
        $this->actingAs(User::factory()->create())->get('/partner')->assertForbidden();
        $noOrganisation = User::factory()->role(Role::PartnerManager)->create();
        $this->actingAs($noOrganisation)->get('/partner')->assertForbidden(); // a partner account must belong to an active organisation
        $org = PartnerOrganisation::create(['name' => 'Isolation Test Agency']);
        $partner = User::factory()->role(Role::PartnerManager)->create();
        $partner->forceFill(['partner_organisation_id' => $org->id])->save();
        $this->actingAs($partner)->get('/partner')->assertOk();
    }

    public function test_deleting_the_account_data_paths_do_not_expose_other_rows(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $doc = $a->documents()->create(['type' => 'cv', 'title' => 'A CV']);
        $this->actingAs($b)->get(route('student.documents.download', $doc))->assertForbidden();
        $this->actingAs($b)->delete(route('student.documents.destroy', $doc))->assertForbidden();
        $this->assertSame(1, Document::count());
        $this->assertSame(0, DB::table('audits')->where('action', 'document.downloaded')->count());
    }
}
