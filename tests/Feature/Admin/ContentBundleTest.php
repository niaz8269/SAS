<?php

namespace Tests\Feature\Admin;

use App\Domain\System\ContentBundle;
use App\Enums\Role;
use App\Models\Country;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class ContentBundleTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'sas').'.jsonl.gz';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_content_survives_a_round_trip_with_ids_kept_and_staff_ids_blanked(): void
    {
        $staff = User::factory()->role(Role::Administrator)->create();
        $china = Country::factory()->create(['name' => 'Bundleland']);
        $cycle = $this->publishedCycle(['external_key' => 'bundle-1', 'verified_by' => $staff->id], null, $china);
        $demo = $this->publishedCycle(['external_key' => 'partner-1-1']);

        $counts = app(ContentBundle::class)->export($this->file);
        $this->assertSame(1, $counts['scholarship_cycles'], 'only the real cycle is exported, not the partner demo one');
        $this->assertSame(1, $counts['scholarship_routes']);
        $this->assertNotNull($demo->id);

        DB::table('scholarship_cycles')->delete(); // dependants are removed by cascade
        DB::table('countries')->where('id', $china->id)->delete();

        app(ContentBundle::class)->import($this->file, replace: true); // a fresh deploy already holds the rows its migrations seed

        $back = ScholarshipCycle::find($cycle->id);
        $this->assertNotNull($back);
        $this->assertNull($back->verified_by, 'staff accounts do not exist on the other side');
        $this->assertSame('Bundleland', Country::find($china->id)->name);
        $this->assertSame($cycle->id, DB::table('cycle_destinations')->where('country_id', $china->id)->value('cycle_id'));
        $this->assertNull(ScholarshipCycle::where('external_key', 'partner-1-1')->first(), 'the demo cycle is never exported');
    }

    public function test_no_people_tables_are_in_the_bundle_and_it_refuses_to_overwrite_a_used_site(): void
    {
        foreach (['users', 'service_cases', 'documents', 'student_profiles', 'audits', 'sessions', 'password_reset_tokens'] as $table) {
            $this->assertNotContains($table, ContentBundle::TABLES);
        }

        $this->publishedCycle(['external_key' => 'bundle-2']);
        app(ContentBundle::class)->export($this->file);

        $this->expectExceptionMessage('not empty');
        app(ContentBundle::class)->import($this->file);
    }

    public function test_replace_is_refused_once_student_data_exists(): void
    {
        $this->publishedCycle(['external_key' => 'bundle-3']);
        app(ContentBundle::class)->export($this->file);
        DB::table('student_profiles')->insert(['user_id' => User::factory()->create()->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectExceptionMessage('student_profiles');
        app(ContentBundle::class)->import($this->file, replace: true);
    }

    public function test_a_file_that_is_not_a_bundle_is_rejected(): void
    {
        $gz = gzopen($this->file, 'wb');
        gzwrite($gz, json_encode(['hello' => 'world'])."\n");
        gzclose($gz);

        $this->expectExceptionMessage('not a SAS content bundle');
        app(ContentBundle::class)->import($this->file, replace: true);
    }
}
