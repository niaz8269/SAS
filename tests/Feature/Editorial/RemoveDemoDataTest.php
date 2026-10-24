<?php

namespace Tests\Feature\Editorial;

use App\Models\Country;
use App\Models\Funder;
use App\Models\PartnerOrganisation;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RemoveDemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_records_and_accounts_are_removed_and_real_ones_are_kept(): void
    {
        $demo = ScholarshipProgramme::factory()->create(['title' => '[DEMO] Fictional award']);
        ScholarshipCycle::factory()->create(['programme_id' => $demo->id]);
        $real = ScholarshipProgramme::factory()->create(['title' => 'Chevening Scholarships']);
        Funder::create(['name' => '[DEMO] Example Foundation', 'slug' => 'demo-ex', 'type' => 'foundation']);
        User::factory()->create(['email' => 'demo-editor@example.test']);
        $owner = User::factory()->create(['email' => 'owner@sas.test']);
        $china = Country::create(['code' => 'CN', 'name' => 'China', 'slug' => 'china', 'summary' => '[DEMO] Placeholder', 'study_guidance' => '<p>[DEMO] text</p>', 'is_destination' => true]);

        $this->artisan('sas:remove-demo-data')->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'demo-editor@example.test']); // logins stay unless asked
        $this->artisan('sas:remove-demo-data', ['--with-accounts' => true])->assertSuccessful();

        $this->assertDatabaseMissing('scholarship_programmes', ['id' => $demo->id]);
        $this->assertSame(0, ScholarshipCycle::where('programme_id', $demo->id)->count());
        $this->assertDatabaseHas('scholarship_programmes', ['id' => $real->id]);
        $this->assertDatabaseMissing('funders', ['slug' => 'demo-ex']);
        $this->assertDatabaseMissing('users', ['email' => 'demo-editor@example.test']);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertStringNotContainsString('DEMO', (string) $china->fresh()->summary.$china->fresh()->study_guidance);
        $this->assertStringContainsString('campuschina.org', $china->fresh()->study_guidance);
    }

    public function test_demo_partner_scholarship_cost_lines_and_demo_agency_are_removed(): void
    {
        $partner = ScholarshipProgramme::factory()->create(['title' => 'Demo Partner Scholarship']);
        ScholarshipCycle::factory()->create(['programme_id' => $partner->id, 'external_key' => 'partner-7']);
        $real = ScholarshipProgramme::factory()->create(['title' => 'Chinese Government Scholarship']);
        $realCycle = ScholarshipCycle::factory()->create(['programme_id' => $real->id]);
        Funder::create(['name' => 'Demo Partner Funder', 'slug' => 'demo-partner', 'type' => 'foundation']);
        $org = PartnerOrganisation::create(['name' => 'Demo Agency', 'kind' => 'agent', 'contact_email' => 'agency@example.test']);
        $agent = User::factory()->create(['email' => 'agent@example.test', 'partner_organisation_id' => $org->id]);
        $realStaff = User::factory()->create(['email' => 'owner@sas.test']);
        $uk = Country::create(['code' => 'GB', 'name' => 'United Kingdom', 'slug' => 'united-kingdom', 'cost_notes' => 'Not real figures, for layout only.']);
        DB::table('notifications')->insert(['id' => 'n1', 'type' => 'x', 'notifiable_type' => User::class, 'notifiable_id' => $agent->id, 'data' => '{}', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('sas:remove-demo-data', ['--with-accounts' => true])->assertSuccessful();

        $this->assertDatabaseMissing('scholarship_programmes', ['id' => $partner->id]);
        $this->assertDatabaseMissing('funders', ['slug' => 'demo-partner']);
        $this->assertDatabaseMissing('partner_organisations', ['id' => $org->id]);
        $this->assertDatabaseMissing('users', ['id' => $agent->id]);
        $this->assertDatabaseMissing('notifications', ['id' => 'n1']);
        $this->assertNull($uk->fresh()->cost_notes);
        $this->assertDatabaseHas('scholarship_programmes', ['id' => $real->id]);
        $this->assertDatabaseHas('scholarship_cycles', ['id' => $realCycle->id]);
        $this->assertDatabaseHas('users', ['id' => $realStaff->id]);
    }

    public function test_it_refuses_to_run_in_production_without_force(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('sas:remove-demo-data')->assertFailed();
    }
}
