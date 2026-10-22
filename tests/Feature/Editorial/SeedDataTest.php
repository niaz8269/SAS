<?php

namespace Tests\Feature\Editorial;

use App\Enums\PublicationState;
use App\Models\ScholarshipCycle;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Guards the researched seed files in data/seed: they must stay valid, draft-only and source-backed. */
class SeedDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_seed_file_validates_and_imports_only_drafts_with_official_sources(): void
    {
        $this->seed(TaxonomySeeder::class);
        $files = glob(base_path('data/seed/*.csv'));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->artisan('sas:import-scholarships', ['file' => $file])->assertSuccessful(); // validation errors would show as warnings and be caught below
        }
        $this->assertSame(0, ScholarshipCycle::count(), 'staging alone must not create anything');
        foreach ($files as $file) {
            $this->artisan('sas:import-scholarships', ['file' => $file, '--commit' => true])->assertSuccessful();
        }

        $cycles = ScholarshipCycle::with('evidence', 'routes', 'currentDeadlines')->get();
        $this->assertGreaterThanOrEqual(13, $cycles->count());
        foreach ($cycles as $c) {
            $this->assertSame(PublicationState::Draft, $c->publication_state, $c->external_key);
            $this->assertFalse($c->fully_funded_verified);
            $this->assertNull($c->published_at);
            $this->assertNotEmpty($c->evidence->first()->url);
            $this->assertNotSame('aggregator', $c->evidence->first()->publisher_type);
            $this->assertSame(['CN'], $c->destinations->pluck('code')->all());
            $this->assertCount(1, $c->currentDeadlines);
        }
        // The only exact-time deadline is one whose source states the hour and zone.
        $exact = $cycles->filter(fn ($c) => $c->currentDeadlines->first()->date_type->value === 'exact')->pluck('external_key')->all();
        $this->assertSame(['cn-pku-grad-2026'], array_values($exact));
        $this->assertSame('2026-03-09 01:00:00', ScholarshipCycle::where('external_key', 'cn-pku-grad-2026')->first()->currentDeadlines->first()->closes_at_utc->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-30', ScholarshipCycle::where('external_key', 'cn-yenching-2027')->first()->currentDeadlines->first()->closes_on->toDateString());

        $before = ScholarshipCycle::count();
        foreach ($files as $file) {
            $this->artisan('sas:import-scholarships', ['file' => $file, '--commit' => true])->assertSuccessful(); // replay is safe
        }
        $this->assertSame($before, ScholarshipCycle::count());
    }
}
