<?php

namespace Tests\Feature\Scholarship;

use App\Enums\PublicationState;
use App\Models\ScholarshipCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Israel Ministry of Foreign Affairs scholarship was removed from the site at the owner's request (13 November 2026) and is kept as a draft. */
class UnpublishIsraelScholarshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_leaves_the_site_but_is_kept_as_a_draft(): void
    {
        $cycle = ScholarshipCycle::factory()->published()->create(['external_key' => 'il-mfa-scholarship-2026']);
        $other = ScholarshipCycle::factory()->published()->create(['external_key' => 'gb-rhodes-2028']);
        $url = route('scholarships.show', [$cycle->programme->slug, $cycle->slug]);
        $this->get($url)->assertOk();

        (require database_path('migrations/2026_12_12_000003_unpublish_israel_scholarships.php'))->up();

        $this->assertSame(PublicationState::Draft, $cycle->fresh()->publication_state);
        $this->get($url)->assertNotFound();
        $this->assertStringNotContainsString($cycle->slug, $this->sitemapXml('scholarships'));
        $this->assertSame(PublicationState::Published, $other->fresh()->publication_state, 'other scholarships are not touched');
    }
}
