<?php

namespace Tests\Feature\Scholarship;

use App\Models\ScholarshipCycle;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** sas:import-details loads what an official source states, replaces only the sections present, never verifies funding, and is safe to repeat. */
class ImportScholarshipDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function file(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'det').'.json';
        file_put_contents($path, json_encode($entries));

        return $path;
    }

    private function entry(): array
    {
        return ['external_key' => 'test-details-1', 'checked_on' => '2026-11-13',
            'sources' => [['url' => 'https://example.gov/scholarship', 'publisher' => 'Example Ministry', 'publisher_type' => 'government']],
            'eligibility_notes' => 'Open to citizens of listed countries.',
            'benefits' => [['type' => 'tuition', 'coverage' => 'covered', 'limit_text' => 'Full tuition'], ['type' => 'bogus', 'coverage' => 'covered']],
            'requirements' => [['document_type' => 'passport', 'text' => 'Valid passport', 'mandatory' => true], ['document_type' => 'weird', 'text' => 'Study plan']],
            'application_steps' => [
                ['phase' => 'apply', 'title' => 'Register online', 'detail' => 'Create an account on the portal.'],
                ['phase' => 'apply', 'title' => 'Submit documents'],
                ['phase' => 'selection', 'title' => 'Committee review', 'detail' => 'Applicants are ranked by the committee.'],
                ['phase' => 'nonsense', 'title' => 'Ignored'],
            ]];
    }

    public function test_it_loads_details_and_the_page_shows_the_process(): void
    {
        $this->seed(TaxonomySeeder::class);
        $cycle = ScholarshipCycle::factory()->published()->create(['external_key' => 'test-details-1', 'funding_type' => 'full', 'fully_funded_verified' => false]);
        $cycle->requirements()->create(['document_type' => 'other', 'text' => 'Old line', 'mandatory' => true]);

        $this->artisan('sas:import-details', ['files' => [$this->file([$this->entry()])]])->assertSuccessful();

        $cycle->refresh()->load('applicationSteps', 'benefits', 'requirements', 'evidence');
        $this->assertSame(['Register online', 'Submit documents', 'Committee review'], $cycle->applicationSteps->pluck('title')->all()); // unknown phase refused
        $this->assertSame(['tuition'], $cycle->benefits->pluck('type')->all()); // unknown benefit type refused
        $this->assertSame(['Valid passport', 'Study plan'], $cycle->requirements->pluck('text')->all()); // replaced as a whole
        $this->assertSame('other', $cycle->requirements->last()->document_type);
        $this->assertSame('Open to citizens of listed countries.', $cycle->eligibility_notes);
        $this->assertTrue($cycle->evidence->contains('url', 'https://example.gov/scholarship'));
        $this->assertFalse((bool) $cycle->fully_funded_verified, 'importing must never verify full funding');

        $this->get(route('scholarships.show', [$cycle->programme->slug, $cycle->slug]))->assertOk()
            ->assertSee('Application process')->assertSee('How to apply')->assertSee('Register online')->assertSee('Create an account on the portal.')
            ->assertSee('How applicants are selected')->assertSee('Committee review')->assertDontSee('Ignored')
            ->assertSee('How do I apply for', false);
    }

    public function test_running_it_again_changes_nothing_and_dry_run_writes_nothing(): void
    {
        $this->seed(TaxonomySeeder::class);
        $cycle = ScholarshipCycle::factory()->published()->create(['external_key' => 'test-details-1']);
        $file = $this->file([$this->entry()]);

        $this->artisan('sas:import-details', ['files' => [$file], '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, $cycle->applicationSteps()->count());

        $this->artisan('sas:import-details', ['files' => [$file]])->assertSuccessful();
        $this->artisan('sas:import-details', ['files' => [$file]])->assertSuccessful();
        $this->assertSame(3, $cycle->applicationSteps()->count());
        $this->assertSame(1, $cycle->benefits()->count());
        $this->assertSame(1, $cycle->evidence()->where('url', 'https://example.gov/scholarship')->count());
    }

    public function test_a_section_that_is_not_in_the_file_is_left_alone(): void
    {
        $this->seed(TaxonomySeeder::class);
        $cycle = ScholarshipCycle::factory()->published()->create(['external_key' => 'test-details-1', 'eligibility_notes' => 'Keep me']);
        $cycle->requirements()->create(['document_type' => 'other', 'text' => 'Kept document', 'mandatory' => true]);

        $this->artisan('sas:import-details', ['files' => [$this->file([['external_key' => 'test-details-1', 'application_steps' => [['phase' => 'apply', 'title' => 'Only this']]]])]])->assertSuccessful();

        $cycle->refresh();
        $this->assertSame('Keep me', $cycle->eligibility_notes);
        $this->assertSame(['Kept document'], $cycle->requirements()->pluck('text')->all());
        $this->assertSame(['Only this'], $cycle->applicationSteps()->pluck('title')->all());
    }
}
