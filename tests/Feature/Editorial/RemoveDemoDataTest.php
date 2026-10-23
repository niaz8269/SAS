<?php

namespace Tests\Feature\Editorial;

use App\Models\Country;
use App\Models\Funder;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->assertDatabaseMissing('scholarship_programmes', ['id' => $demo->id]);
        $this->assertSame(0, ScholarshipCycle::where('programme_id', $demo->id)->count());
        $this->assertDatabaseHas('scholarship_programmes', ['id' => $real->id]);
        $this->assertDatabaseMissing('funders', ['slug' => 'demo-ex']);
        $this->assertDatabaseMissing('users', ['email' => 'demo-editor@example.test']);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertStringNotContainsString('DEMO', (string) $china->fresh()->summary.$china->fresh()->study_guidance);
        $this->assertStringContainsString('campuschina.org', $china->fresh()->study_guidance);
    }

    public function test_it_refuses_to_run_in_production_without_force(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('sas:remove-demo-data')->assertFailed();
    }
}
