<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** The super admin adds Admins and Counsellors, and later changes their names, sign-in emails and passwords. */
class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actAs(Role $role): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    public function test_the_super_admin_adds_several_admins_and_counsellors_who_each_get_a_link_to_choose_a_password(): void
    {
        Notification::fake();
        $this->actAs(Role::SuperAdmin);

        foreach ([['Amna Khan', 'amna@cas.example', 'administrator'], ['Bilal Shah', 'bilal@cas.example', 'advisor'], ['Sara Ali', 'sara@cas.example', 'advisor']] as [$name, $email, $role]) {
            Livewire::test(ListUsers::class)->callAction('addStaff', ['name' => $name, 'email' => strtoupper($email), 'role' => $role])->assertHasNoActionErrors();
            $user = User::where('email', $email)->firstOrFail();
            $this->assertTrue($user->hasRole(Role::from($role)));
            $this->assertNotNull($user->email_verified_at);
            Notification::assertSentTo($user, ResetPassword::class);
        }
        $this->assertSame(3, User::whereHas('userRoles', fn ($q) => $q->whereIn('role', ['administrator', 'advisor']))->count());
        $this->assertDatabaseHas('audits', ['action' => 'user.staff_added']);
    }

    public function test_only_a_super_admin_can_add_staff_and_a_new_super_admin_cannot_be_made_from_the_form(): void
    {
        $this->actAs(Role::Administrator);
        Livewire::test(ListUsers::class)->assertActionHidden('addStaff');

        auth()->logout();
        $this->actAs(Role::SuperAdmin);
        Livewire::test(ListUsers::class)->callAction('addStaff', ['name' => 'Eve', 'email' => 'eve@cas.example', 'role' => 'super_admin'])->assertHasActionErrors(['role']);
        $this->assertDatabaseMissing('users', ['email' => 'eve@cas.example']);
        Livewire::test(ListUsers::class)->callAction('addStaff', ['name' => 'Eve', 'email' => 'eve@cas.example', 'role' => 'advisor']);
        Livewire::test(ListUsers::class)->callAction('addStaff', ['name' => 'Eve Two', 'email' => 'EVE@cas.example', 'role' => 'advisor'])->assertHasActionErrors(['email']);
    }

    public function test_the_super_admin_changes_a_name_email_and_password_and_the_old_sessions_end(): void
    {
        $super = $this->actAs(Role::SuperAdmin);
        $admin = User::factory()->role(Role::Administrator)->create(['email' => 'admin@sas.test', 'name' => 'Admin']);
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()]);

        Livewire::test(EditUser::class, ['record' => $admin->getKey()])
            ->fillForm(['name' => 'Amna Khan', 'email' => 'Amna@Cas.example', 'status' => 'active', 'roles' => ['administrator'], 'new_password' => 'Brand-New-Pass-77', 'new_password_confirmation' => 'Brand-New-Pass-77'])
            ->call('save')->assertHasNoFormErrors();

        $admin->refresh();
        $this->assertSame('Amna Khan', $admin->name);
        $this->assertSame('amna@cas.example', $admin->email);
        $this->assertTrue(Hash::check('Brand-New-Pass-77', $admin->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'abc']);
        $this->assertDatabaseHas('audits', ['action' => 'user.email_changed']);
        $this->assertDatabaseHas('audits', ['action' => 'user.password_set']);
        $this->assertStringNotContainsString('Brand-New-Pass-77', json_encode(DB::table('audits')->get()));
        $this->assertSame($super->id, auth()->id());
    }

    public function test_a_weak_or_mismatched_password_and_a_taken_email_are_refused(): void
    {
        $this->actAs(Role::SuperAdmin);
        $admin = User::factory()->role(Role::Administrator)->create(['email' => 'admin@sas.test']);
        User::factory()->create(['email' => 'taken@sas.test']);

        $page = Livewire::test(EditUser::class, ['record' => $admin->getKey()]);
        $page->fillForm(['name' => 'A', 'email' => 'admin@sas.test', 'status' => 'active', 'roles' => ['administrator'], 'new_password' => 'short', 'new_password_confirmation' => 'short'])->call('save')->assertHasFormErrors(['new_password']);
        $page->fillForm(['new_password' => 'Long-Enough-Pass-9', 'new_password_confirmation' => 'Different-Pass-9'])->call('save')->assertHasFormErrors(['new_password']);
        $page->fillForm(['new_password' => null, 'new_password_confirmation' => null, 'email' => 'taken@sas.test'])->call('save')->assertHasFormErrors(['email']);
        $this->assertSame('admin@sas.test', $admin->fresh()->email);
    }

    public function test_an_admin_can_not_change_anyones_email_or_password(): void
    {
        $this->actAs(Role::Administrator);
        $counsellor = User::factory()->role(Role::Advisor)->create(['email' => 'c@sas.test']);
        $before = $counsellor->password;

        Livewire::test(EditUser::class, ['record' => $counsellor->getKey()])
            ->assertActionHidden('sendResetLink')
            ->fillForm(['name' => 'Renamed', 'email' => 'hacked@sas.test', 'status' => 'active', 'new_password' => 'Sneaky-Password-12', 'new_password_confirmation' => 'Sneaky-Password-12'])
            ->call('save');

        $counsellor->refresh();
        $this->assertSame('Renamed', $counsellor->name);
        $this->assertSame('c@sas.test', $counsellor->email);
        $this->assertSame($before, $counsellor->password);
    }

    public function test_the_reset_link_action_emails_the_person(): void
    {
        Notification::fake();
        $this->actAs(Role::SuperAdmin);
        $counsellor = User::factory()->role(Role::Advisor)->create();

        Livewire::test(EditUser::class, ['record' => $counsellor->getKey()])->callAction('sendResetLink');
        Notification::assertSentTo($counsellor, ResetPassword::class);
    }

    public function test_the_super_admin_deletes_a_user_which_archives_the_account_and_keeps_their_data(): void
    {
        $super = $this->actAs(Role::SuperAdmin);
        $counsellor = User::factory()->role(Role::Advisor)->create(['email' => 'c@sas.test']);
        $student = User::factory()->create(['counsellor_id' => $counsellor->id]);
        DB::table('sessions')->insert(['id' => 'xyz', 'user_id' => $counsellor->id, 'payload' => '', 'last_activity' => time()]);

        Livewire::test(ListUsers::class)->callTableAction('archiveUser', $counsellor)->assertNotified();

        $counsellor->refresh();
        $this->assertSame('archived', $counsellor->status);
        $this->assertFalse($counsellor->isStaff());
        $this->assertSame([], $counsellor->fresh()->roleKeys());
        $this->assertDatabaseMissing('sessions', ['id' => 'xyz']);
        $this->assertSame($counsellor->id, $student->fresh()->counsellor_id); // the row stays, so what points at it still works
        $this->assertDatabaseHas('audits', ['action' => 'user.archived']);

        // hidden from the default list, found under "Deleted only"
        Livewire::test(ListUsers::class)->assertCanNotSeeTableRecords([$counsellor])->assertCanSeeTableRecords([$super])
            ->filterTable('archived', true)->assertCanSeeTableRecords([$counsellor]);

        // restoring: set the status back to Active, then grant roles again
        Livewire::test(EditUser::class, ['record' => $counsellor->getKey()])
            ->fillForm(['name' => $counsellor->name, 'status' => 'active', 'roles' => ['advisor']])->call('save')->assertHasNoFormErrors();
        $this->assertTrue($counsellor->fresh()->isStaff());
    }

    public function test_only_the_super_admin_can_delete_users_and_nobody_can_delete_themselves(): void
    {
        $admin = $this->actAs(Role::Administrator);
        $counsellor = User::factory()->role(Role::Advisor)->create();
        Livewire::test(ListUsers::class)->assertTableActionHidden('archiveUser', $counsellor);
        Livewire::test(EditUser::class, ['record' => $counsellor->getKey()])->assertActionHidden('archiveUser');

        auth()->logout();
        $super = $this->actAs(Role::SuperAdmin);
        Livewire::test(ListUsers::class)->assertTableActionHidden('archiveUser', $super)->assertTableActionVisible('archiveUser', $admin);
        Livewire::test(EditUser::class, ['record' => $counsellor->getKey()])->callAction('archiveUser')->assertNotified();
        $this->assertSame('archived', $counsellor->fresh()->status);
        $this->assertTrue($super->fresh()->isStaff());
    }

    public function test_a_deleted_account_cannot_sign_in_to_the_admin(): void
    {
        $user = User::factory()->role(Role::Administrator)->create(['email' => 'gone@sas.test']);
        $user->archive();
        Filament::setCurrentPanel('admin');
        $this->assertFalse($user->fresh()->canAccessPanel(Filament::getCurrentPanel()));
        $this->assertFalse($user->fresh()->hasPermission('users.manage'));
    }

    public function test_the_reset_command_keeps_one_super_admin_deletes_other_staff_and_keeps_students_and_their_data(): void
    {
        Notification::fake();
        $owner = User::factory()->role(Role::SuperAdmin)->create(['email' => 'owner@sas.test']);
        $admin = User::factory()->role(Role::Administrator)->create(['email' => 'admin@sas.test']);
        $counsellor = User::factory()->role(Role::Advisor)->create(['email' => 'counsellor@sas.test']);
        $student = User::factory()->create(['email' => 'student@example.test', 'counsellor_id' => $counsellor->id]);
        $partner = User::factory()->role(Role::PartnerManager)->create(['email' => 'agent@example.test']);

        // without --yes nothing changes
        $this->artisan('sas:reset-staff', ['email' => 'Boss@Cas.example', 'name' => 'The Boss'])->assertSuccessful();
        $this->assertSame(5, User::count());

        $this->artisan('sas:reset-staff', ['email' => 'Boss@Cas.example', 'name' => 'The Boss', '--yes' => true])->assertSuccessful();

        $boss = User::where('email', 'boss@cas.example')->firstOrFail();
        Notification::assertSentTo($boss, ResetPassword::class);
        $this->assertTrue($boss->isStaff());
        $this->assertSame(['super_admin'], $boss->roleKeys());
        $this->assertSame(1, User::whereHas('userRoles', fn ($q) => $q->whereIn('role', config('sas.staff_roles')))->count());
        foreach ([$owner, $admin, $counsellor] as $gone) {
            $this->assertDatabaseMissing('users', ['id' => $gone->id]);
        }
        $this->assertDatabaseHas('users', ['id' => $student->id, 'counsellor_id' => null]); // the student stays; only the assigned name is empty
        $this->assertDatabaseHas('users', ['id' => $partner->id]);
        $this->assertDatabaseHas('audits', ['action' => 'user.staff_reset']);

        // running it again with the same email changes nothing more
        $this->artisan('sas:reset-staff', ['email' => 'boss@cas.example', 'name' => 'x', '--yes' => true, '--no-mail' => true])->assertSuccessful();
        $this->assertSame(3, User::count());
    }

    public function test_the_reset_command_refuses_a_student_email(): void
    {
        User::factory()->create(['email' => 'student@example.test'])->studentProfile()->create(['first_name' => 'S']);
        $this->artisan('sas:reset-staff', ['email' => 'student@example.test', 'name' => 'x', '--yes' => true])->assertFailed();
    }

    public function test_the_starter_command_creates_exactly_one_of_each_with_passwords_only_in_a_private_file(): void
    {
        $file = sys_get_temp_dir().'/starter-'.uniqid().'.txt';
        $this->artisan('sas:create-starter-staff', ['--out' => $file])->assertSuccessful()->doesntExpectOutputToContain('@');

        $this->assertSame(1, User::whereHas('userRoles', fn ($q) => $q->where('role', 'super_admin'))->count());
        $this->assertSame(1, User::whereHas('userRoles', fn ($q) => $q->where('role', 'administrator'))->count());
        $this->assertSame(1, User::whereHas('userRoles', fn ($q) => $q->where('role', 'advisor'))->count());
        $text = file_get_contents($file);
        preg_match('/owner@sas\.test\s+(\S+)/', $text, $m);
        $this->assertTrue(Hash::check($m[1], User::where('email', 'owner@sas.test')->value('password')));

        $this->artisan('sas:create-starter-staff', ['--out' => $file])->assertSuccessful(); // running it twice adds nobody
        $this->assertSame(3, User::count());
        $this->artisan('sas:create-starter-staff', ['--out' => $file, '--counsellor' => 'owner@sas.test'])->assertFailed();
        @unlink($file);
    }
}
