<?php

namespace Tests\Feature\Editorial;

use App\Models\DegreeLevel;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class ImportOfferingsTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    private function file(array $entry): string
    {
        $f = tempnam(sys_get_temp_dir(), 'of').'.json';
        file_put_contents($f, json_encode([$entry]));

        return $f;
    }

    public function test_offerings_are_imported_levels_aligned_and_the_page_shows_them(): void
    {
        $this->seed(TaxonomySeeder::class);
        $cycle = $this->publishedCycle([], $this->exactDeadline());
        $cycle->forceFill(['external_key' => 'offer-1'])->save();
        $cycle->degreeLevels()->sync(DegreeLevel::whereIn('code', ['bachelor', 'master'])->pluck('id'));

        $entry = ['external_key' => 'offer-1', 'status' => 'confirmed', 'levels_not_offered' => ['bachelor'], 'levels' => [
            ['code' => 'master', 'confirmed' => true, 'summary' => ['detail' => 'Two-year taught master, English', 'url' => 'https://example.org/m'],
                'programmes' => [['name' => 'MSc Data Science', 'institution' => 'University X', 'url' => 'https://example.org/ds']]],
            ['code' => 'doctorate', 'confirmed' => true, 'summary' => ['detail' => 'Three-year PhD']],
        ]];
        $this->artisan('sas:import-offerings', ['files' => [$this->file($entry)]])->assertSuccessful();
        $this->artisan('sas:import-offerings', ['files' => [$this->file($entry)]])->assertSuccessful(); // idempotent

        $cycle->refresh();
        $this->assertEqualsCanonicalizing(['master', 'doctorate'], $cycle->degreeLevels()->pluck('code')->all());
        $this->assertSame(3, $cycle->offerings()->count());
        $this->get(route('scholarships.show', [$cycle->programme->slug, $cycle->slug]))->assertOk()
            ->assertSee('MSc Data Science')->assertSee('University X')->assertSee('Two-year taught master, English')->assertSee('Three-year PhD');
    }

    public function test_a_reload_replaces_only_the_levels_in_the_file_and_never_shortens_a_stored_list(): void
    {
        $this->seed(TaxonomySeeder::class);
        $cycle = $this->publishedCycle([], $this->exactDeadline());
        $cycle->forceFill(['external_key' => 'offer-3'])->save();
        $cycle->degreeLevels()->sync(DegreeLevel::whereIn('code', ['bachelor', 'master'])->pluck('id'));

        $first = ['external_key' => 'offer-3', 'levels' => [
            ['code' => 'bachelor', 'confirmed' => true, 'summary' => ['detail' => 'Four-year bachelor']],
            ['code' => 'master', 'confirmed' => true, 'summary' => ['detail' => 'Old master line'],
                'programmes' => [['name' => 'MSc One'], ['name' => 'MSc Two'], ['name' => 'MSc Three']]],
        ]];
        $this->artisan('sas:import-offerings', ['files' => [$this->file($first)]])->assertSuccessful();

        // The reload covers only the master's level, and names fewer programmes.
        $second = ['external_key' => 'offer-3', 'levels' => [
            ['code' => 'master', 'confirmed' => true, 'summary' => ['detail' => 'New master line'], 'programmes' => [['name' => 'MSc One']]],
        ]];
        $this->artisan('sas:import-offerings', ['files' => [$this->file($second)]])->assertSuccessful();

        $this->assertSame('Four-year bachelor', $cycle->offerings()->whereNull('name')->whereHas('degreeLevel', fn ($q) => $q->where('code', 'bachelor'))->value('detail'), 'the bachelor line was not in the file, so it stays');
        $this->assertSame(3, $cycle->offerings()->whereNotNull('name')->count(), 'the three stored master programmes stay');
        $this->assertSame('New master line', $cycle->offerings()->whereNull('name')->whereHas('degreeLevel', fn ($q) => $q->where('code', 'master'))->value('detail'), 'the level summary is refreshed');
    }

    public function test_dry_run_changes_nothing_and_unconfirmed_levels_are_left_alone(): void
    {
        $this->seed(TaxonomySeeder::class);
        $cycle = $this->publishedCycle([], $this->exactDeadline());
        $cycle->forceFill(['external_key' => 'offer-2'])->save();
        $cycle->degreeLevels()->sync(DegreeLevel::where('code', 'bachelor')->pluck('id'));

        $entry = ['external_key' => 'offer-2', 'levels' => [['code' => 'master', 'confirmed' => true, 'summary' => ['detail' => 'Master']]]];
        $this->artisan('sas:import-offerings', ['files' => [$this->file($entry)], '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, $cycle->offerings()->count());

        $this->artisan('sas:import-offerings', ['files' => [$this->file($entry)]])->assertSuccessful();
        $this->assertEqualsCanonicalizing(['bachelor', 'master'], $cycle->degreeLevels()->pluck('code')->all());
    }
}
