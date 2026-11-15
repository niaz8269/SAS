<?php

namespace Tests\Feature\Editorial;

use App\Models\Country;
use App\Models\CycleMilestone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class ApplyGapFixesTest extends TestCase
{
    use BuildsScholarships;
    use RefreshDatabase;

    private function file(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gap');
        file_put_contents($path, json_encode($entries));

        return $path;
    }

    public function test_it_fills_blanks_only_and_never_replaces_stored_values(): void
    {
        $filled = $this->publishedCycle(['external_key' => 'xx-blank-2027', 'teaching_language' => 'Not stated on the pages read']);
        $kept = $this->publishedCycle(['external_key' => 'xx-kept-2027', 'teaching_language' => 'German']);
        $pk = Country::firstOrCreate(['code' => 'PK'], ['name' => 'Pakistan', 'slug' => 'pakistan', 'region' => 'Asia']);

        $path = $this->file([
            ['external_key' => 'xx-blank-2027', 'teaching_language' => 'English', 'nationality_codes' => ['PK'],
                'milestones' => [['kind' => 'other', 'label' => 'Application deadline (reported by a site)', 'date_type' => 'month', 'starts_on' => '2027-01-01', 'time_text' => 'January 2027', 'source_text' => 'One site reports January.']]],
            ['external_key' => 'xx-kept-2027', 'teaching_language' => 'English'],
            ['external_key' => 'xx-missing-2027', 'teaching_language' => 'English'],
        ]);

        $this->artisan('sas:apply-gap-fixes', ['files' => [$path]])->assertSuccessful();

        $this->assertSame('English', $filled->fresh()->teaching_language);
        $this->assertSame('German', $kept->fresh()->teaching_language);
        $this->assertTrue($filled->fresh()->nationalities->contains($pk));
        $this->assertSame(1, CycleMilestone::where('cycle_id', $filled->id)->count());

        // Running it again changes nothing.
        $this->artisan('sas:apply-gap-fixes', ['files' => [$path]])->assertSuccessful();
        $this->assertSame(1, CycleMilestone::where('cycle_id', $filled->id)->count());
    }

    public function test_a_date_without_a_source_text_is_skipped_and_the_dry_run_changes_nothing(): void
    {
        $cycle = $this->publishedCycle(['external_key' => 'xx-dates-2027']);
        $path = $this->file([['external_key' => 'xx-dates-2027', 'milestones' => [
            ['kind' => 'other', 'label' => 'Application deadline', 'date_type' => 'exact', 'starts_on' => '2027-01-01'],
            ['kind' => 'results', 'label' => 'Results', 'date_type' => 'month', 'starts_on' => '2027-04-01', 'time_text' => 'April 2027', 'source_text' => 'Stated by the funder.'],
        ]]]);

        $this->artisan('sas:apply-gap-fixes', ['files' => [$path], '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, CycleMilestone::where('cycle_id', $cycle->id)->count());

        $this->artisan('sas:apply-gap-fixes', ['files' => [$path]])->assertSuccessful();
        $this->assertSame(['Results'], CycleMilestone::where('cycle_id', $cycle->id)->pluck('label')->all());
    }
}
