<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Models\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Staff work in three roles: Counsellor, Admin and Super admin. */
class StaffRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_are_exactly_three_staff_roles_with_plain_names(): void
    {
        config(['sas.staff_roles' => (require config_path('sas.php'))['staff_roles']]); // the real setting, without the stand-in roles some other tests add
        $this->assertSame(['super_admin', 'administrator', 'advisor'], config('sas.staff_roles'));
        $this->assertSame(['super_admin' => 'Super admin', 'administrator' => 'Admin', 'advisor' => 'Counsellor'], Role::staffOptions());
        $this->assertSame('Partner manager', Role::PartnerManager->label(), 'agents keep their own portal role');
    }

    public function test_an_admin_covers_what_the_old_manager_editor_reviewer_and_finance_roles_did(): void
    {
        $admin = User::factory()->role(Role::Administrator)->create();
        foreach (['content.draft', 'content.review', 'content.publish', 'content.override', 'import.stage', 'articles.manage', 'taxonomy.manage', 'corrections.manage', 'site.manage',
            'enquiries.view_all', 'enquiries.assign', 'services.manage', 'cases.manage', 'finance.view', 'invoices.manage', 'partners.manage', 'documents.manage', 'students.manage',
            'users.manage', 'audit.view', 'reports.view', 'students.assigned', 'cases.assigned', 'enquiries.assigned', 'consultations.assigned'] as $permission) {
            $this->assertTrue($admin->hasPermission($permission), "an admin can $permission");
        }
        $this->assertFalse($admin->hasPermission('roles.manage'), 'only the super admin decides who holds which role');
    }

    public function test_a_counsellor_looks_after_students_and_nothing_else(): void
    {
        $counsellor = User::factory()->role(Role::Advisor)->create();
        foreach (['students.assigned', 'cases.assigned', 'enquiries.assigned', 'consultations.assigned'] as $permission) {
            $this->assertTrue($counsellor->hasPermission($permission));
        }
        foreach (['students.manage', 'cases.manage', 'content.publish', 'invoices.manage', 'site.manage', 'users.manage', 'roles.manage', 'partners.manage', 'services.manage'] as $permission) {
            $this->assertFalse($counsellor->hasPermission($permission), "a counsellor cannot $permission");
        }
    }

    public function test_the_super_admin_can_do_everything_including_roles(): void
    {
        $super = User::factory()->role(Role::SuperAdmin)->create();
        $this->assertTrue($super->hasPermission('roles.manage'));
        $this->assertTrue($super->hasPermission('content.publish'));
        $this->assertTrue($super->hasPermission('students.manage'));
    }

    public function test_counsellors_admins_and_the_super_admin_can_all_be_given_students_and_enquiries(): void
    {
        $counsellor = User::factory()->role(Role::Advisor)->create();
        $admin = User::factory()->role(Role::Administrator)->create();
        $super = User::factory()->role(Role::SuperAdmin)->create();
        $partner = User::factory()->role(Role::PartnerManager)->create();
        $suspended = User::factory()->role(Role::Advisor)->create(['status' => 'suspended']);

        $ids = User::counsellors()->pluck('id')->all();
        foreach ([$counsellor, $admin, $super] as $person) {
            $this->assertContains($person->id, $ids);
        }
        $this->assertNotContains($partner->id, $ids);
        $this->assertNotContains($suspended->id, $ids);
    }

    public function test_people_who_held_the_old_roles_keep_their_access_under_the_new_ones(): void
    {
        $editorAndFinance = User::factory()->create();
        $manager = User::factory()->create();
        $specialist = User::factory()->create();
        $both = User::factory()->create();
        foreach ([[$editorAndFinance, 'editor'], [$editorAndFinance, 'finance'], [$manager, 'service_manager'], [$specialist, 'specialist'], [$both, 'reviewer'], [$both, 'administrator']] as [$user, $role]) {
            UserRole::create(['user_id' => $user->id, 'role' => $role]);
        }

        (require database_path('migrations/2026_11_20_000001_merge_staff_roles.php'))->up();

        $this->assertSame(['administrator'], $editorAndFinance->fresh()->roleKeys());
        $this->assertSame(['administrator'], $manager->fresh()->roleKeys());
        $this->assertSame(['advisor'], $specialist->fresh()->roleKeys());
        $this->assertSame(['administrator'], $both->fresh()->roleKeys());
        $this->assertTrue($manager->fresh()->isStaff());
        $this->assertSame(0, UserRole::whereIn('role', ['editor', 'reviewer', 'finance', 'service_manager', 'specialist'])->count());
    }

    public function test_the_users_screen_offers_only_the_three_roles_and_leaves_an_agents_role_alone(): void
    {
        Filament::setCurrentPanel('admin');
        $super = User::factory()->role(Role::SuperAdmin)->create();
        $agent = User::factory()->role(Role::PartnerManager, Role::Advisor)->create();
        $this->actingAs($super);

        Livewire::test(EditUser::class, ['record' => $agent->getKey()])->fillForm(['name' => $agent->name, 'status' => 'active', 'roles' => ['administrator']])->call('save');

        $roles = $agent->fresh()->roleKeys();
        sort($roles);
        $this->assertSame(['administrator', 'partner_manager'], $roles, 'the staff role changed; the agent role stayed');
        Livewire::test(EditUser::class, ['record' => $agent->getKey()])->fillForm(['roles' => ['service_manager']])->call('save'); // not a role any more: ignored
        $this->assertNotContains('service_manager', $agent->fresh()->roleKeys());
    }
}
