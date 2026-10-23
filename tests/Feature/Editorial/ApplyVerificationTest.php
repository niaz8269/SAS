<?php

namespace Tests\Feature\Editorial;

use App\Models\CycleMilestone;
use App\Models\DegreeLevel;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class ApplyVerificationTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    private function file(array $entry): string
    {
        $f = tempnam(sys_get_temp_dir(), 'vf').'.json';
        file_put_contents($f, json_encode([$entry]));

        return $f;
    }

    public function test_title_degrees_opening_and_closing_are_updated_and_the_old_deadline_stays_in_history(): void
    {
        $this->seed(TaxonomySeeder::class);
        $cycle = $this->publishedCycle([], $this->exactDeadline());
        $key = 'verify-test-1';
        $cycle->forceFill(['external_key' => $key])->save();
        $cycle->degreeLevels()->sync([DegreeLevel::where('code', 'bachelor')->value('id')]);

        $this->artisan('sas:apply-verification', ['files' => [$this->file([
            'external_key' => $key, 'status' => 'confirmed', 'official_title' => 'Official Scholarship Title', 'title_action' => 'change',
            'degrees' => ['codes' => ['master', 'doctorate'], 'confirmed' => true],
            'opens' => ['date_type' => 'month', 'starts_on' => '2026-11-01', 'phrase' => 'November 2026', 'source_text' => 'Opens in November 2026', 'url' => 'https://example.org/p'],
            'closes' => ['type' => 'date_only', 'date' => '2027-01-15', 'source_text' => '15 January 2027', 'url' => 'https://example.org/p'],
        ])]])->assertSuccessful();

        $cycle->refresh()->load('programme', 'degreeLevels', 'milestones');
        $this->assertSame('Official Scholarship Title', $cycle->programme->title);
        $this->assertEqualsCanonicalizing(['master', 'doctorate'], $cycle->degreeLevels->pluck('code')->all());
        $this->assertSame('November 2026', $cycle->milestones->firstWhere('kind', 'opens')->display());
        $this->assertSame(2, $cycle->deadlines()->count()); // the old version is superseded, not deleted
        $this->assertSame('2027-01-15', $cycle->currentDeadlines()->first()->closes_on->toDateString());
        $this->assertNotNull($cycle->last_verified_at);
    }

    public function test_not_announced_never_erases_a_recorded_date_and_dry_run_changes_nothing(): void
    {
        $cycle = $this->publishedCycle([], $this->exactDeadline());
        $cycle->forceFill(['external_key' => 'verify-test-2'])->save();
        CycleMilestone::create(['cycle_id' => $cycle->id, 'kind' => 'opens', 'date_type' => 'date_only', 'starts_on' => '2026-09-01']);
        $before = $cycle->currentDeadlines()->first()->closes_at_utc;

        $entry = ['external_key' => 'verify-test-2', 'status' => 'partly', 'opens' => ['date_type' => 'not_announced'], 'closes' => ['type' => 'not_announced']];
        $this->artisan('sas:apply-verification', ['files' => [$this->file($entry)]])->assertSuccessful();
        $this->assertSame('1 September 2026', $cycle->milestones()->where('kind', 'opens')->first()->display());
        $this->assertTrue($before->equalTo($cycle->currentDeadlines()->first()->closes_at_utc));

        $entry['closes'] = ['type' => 'date_only', 'date' => '2030-01-01'];
        $this->artisan('sas:apply-verification', ['files' => [$this->file($entry)], '--dry-run' => true])->assertSuccessful();
        $this->assertTrue($before->equalTo($cycle->currentDeadlines()->first()->closes_at_utc));
    }

    public function test_a_dated_deadline_is_not_downgraded_to_varies_by_route(): void
    {
        $cycle = $this->publishedCycle([], $this->exactDeadline());
        $cycle->forceFill(['external_key' => 'verify-test-3'])->save();
        $before = $cycle->currentDeadlines()->first()->id;
        $this->artisan('sas:apply-verification', ['files' => [$this->file(['external_key' => 'verify-test-3', 'status' => 'partly', 'closes' => ['type' => 'route_dependent', 'source_text' => 'varies']])]])->assertSuccessful();
        $this->assertSame($before, $cycle->currentDeadlines()->first()->id);
    }
}
