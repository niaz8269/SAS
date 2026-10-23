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
