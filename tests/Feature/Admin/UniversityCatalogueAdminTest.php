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

    public function test_staff_can_browse_filter_and_search_programmes_in_english_or_chinese(): void
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

        $this->assertTrue(UniversityProgrammeResource::canEdit($p));
        $this->assertTrue(UniversityProgrammeResource::canCreate());
    }

    public function test_searching_the_programme_list_by_a_word_or_a_university_name_does_not_error(): void
    {
        $this->staff(Role::SuperAdmin);
        $china = Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china']);
        $anhui = University::create(['name' => 'Anhui University', 'slug' => 'anhui', 'name_zh' => '安徽大学', 'province' => '安徽', 'country_id' => $china->id]);
        $other = University::create(['name' => 'Other University', 'slug' => 'other', 'name_zh' => '其他大学', 'province' => '北京', 'country_id' => $china->id]);
        $software = UniversityProgramme::create(['university_id' => $anhui->id, 'level' => 'bachelor', 'name_zh' => '软件工程', 'name_en' => 'Software Engineering', 'teaching_language' => '汉语']);
        $law = UniversityProgramme::create(['university_id' => $other->id, 'level' => 'master', 'name_zh' => '法学', 'name_en' => 'Law', 'teaching_language' => '英语']);

        Livewire::test(ManageUniversityProgrammes::class)
            ->searchTable('software')->assertCanSeeTableRecords([$software])->assertCanNotSeeTableRecords([$law])
            ->searchTable('Anhui')->assertCanSeeTableRecords([$software])->assertCanNotSeeTableRecords([$law])
            ->searchTable('其他')->assertCanSeeTableRecords([$law])->assertCanNotSeeTableRecords([$software]);
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
        foreach ([Role::SuperAdmin, Role::Administrator, Role::Administrator] as $role) {
            $this->staff($role);
            $html = $this->get('/admin')->assertOk()->getContent();
            $this->assertStringContainsString('/admin/universities', $html);
            $this->assertStringContainsString('/admin/university-programmes', $html);
            $this->get('/admin/universities')->assertOk();
        }
    }
}
