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

    public function test_the_china_priority_file_validates_and_imports_only_drafts_with_official_sources(): void
    {
        $this->seed(TaxonomySeeder::class);
        $file = base_path('data/seed/china-priority-2026-10.csv');

        $this->artisan('sas:import-scholarships', ['file' => $file])->assertSuccessful();
        $this->assertSame(0, ScholarshipCycle::count(), 'staging alone must not create anything');

        $this->artisan('sas:import-scholarships', ['file' => $file, '--commit' => true])->assertSuccessful();

        $cycles = ScholarshipCycle::with('evidence', 'routes', 'currentDeadlines')->get();
        $this->assertCount(5, $cycles);
        foreach ($cycles as $c) {
            $this->assertSame(PublicationState::Draft, $c->publication_state);
            $this->assertFalse($c->fully_funded_verified);
            $this->assertNull($c->published_at);
            $this->assertNotEmpty($c->evidence->first()->url);
            $this->assertNotSame('aggregator', $c->evidence->first()->publisher_type);
            $this->assertSame(['CN'], $c->destinations->pluck('code')->all());
            $this->assertCount(1, $c->currentDeadlines);
            // No invented closing times: nothing in this file is an exact-time deadline.
            $this->assertNotSame('exact', $c->currentDeadlines->first()->date_type->value);
        }
        $this->assertSame('2026-11-30', ScholarshipCycle::where('external_key', 'cn-yenching-2027')->first()->currentDeadlines->first()->closes_on->toDateString());

        $this->artisan('sas:import-scholarships', ['file' => $file, '--commit' => true])->assertSuccessful(); // replay
        $this->assertSame(5, ScholarshipCycle::count());
    }
}
