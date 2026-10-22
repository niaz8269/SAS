<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\Universities\Pages\ManageUniversities;
use App\Filament\Resources\UniversityProgrammes\Pages\ManageUniversityProgrammes;
use App\Filament\Resources\UniversityProgrammes\UniversityProgrammeResource;
use App\Models\Country;
use App\Models\University;
use App\Models\UniversityProgramme;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UniversityCatalogueAdminTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Role $role): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    private function catalogue(): array
    {
        $china = Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china']);
        $a = University::create(['name' => 'A', 'slug' => 'a', 'name_zh' => '甲大学', 'province' => '北京', 'country_id' => $china->id]);
        $b = University::create(['name' => 'B', 'slug' => 'b', 'name_zh' => '乙大学', 'province' => '上海', 'country_id' => $china->id]);
        $p = UniversityProgramme::create(['university_id' => $a->id, 'level' => 'master', 'name_zh' => '法学', 'csc_eligible' => true, 'teaching_language' => '英语', 'tuition_cny' => 20000]);
        UniversityProgramme::create(['university_id' => $a->id, 'level' => 'bachelor', 'name_zh' => '医学', 'csc_eligible' => false, 'teaching_language' => '汉语']);

        return [$a, $b, $p];
    }

    public function test_editor_can_browse_and_filter_programmes_but_not_change_them(): void
    {
        $this->staff(Role::SuperAdmin);
        [$a, , $p] = $this->catalogue();

        Livewire::test(ManageUniversityProgrammes::class)
            ->assertCanSeeTableRecords(UniversityProgramme::all())
            ->filterTable('level', 'master')
            ->assertCanSeeTableRecords([$p])
            ->assertCountTableRecords(1)
            ->searchTable('法学')
            ->assertCountTableRecords(1);

        $this->assertFalse(UniversityProgrammeResource::canEdit($p));
        $this->assertFalse(UniversityProgrammeResource::canCreate());
    }

    public function test_university_list_shows_programme_counts_and_filters_by_having_programmes(): void
    {
        $this->staff(Role::SuperAdmin);
        [$a, $b] = $this->catalogue();

        Livewire::test(ManageUniversities::class)
            ->assertCanSeeTableRecords([$a, $b])
            ->filterTable('has_programmes', false)
            ->assertCanSeeTableRecords([$b])
            ->assertCanNotSeeTableRecords([$a])
            ->resetTableFilters()
            ->filterTable('province', '北京')
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b]);
    }

    public function test_student_cannot_open_the_programme_list(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create())->get('/admin/university-programmes')->assertForbidden();
    }

    public function test_universities_appear_in_the_sidebar_for_every_role_that_manages_reference_data(): void
    {
        foreach ([Role::SuperAdmin, Role::Administrator, Role::Editor, Role::Reviewer] as $role) {
            $this->staff($role);
            $html = $this->get('/admin')->assertOk()->getContent();
            $this->assertStringContainsString('/admin/universities', $html);
            $this->assertStringContainsString('/admin/university-programmes', $html);
            $this->get('/admin/universities')->assertOk();
        }
    }
}
