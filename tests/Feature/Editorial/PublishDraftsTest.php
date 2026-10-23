<?php

namespace Tests\Feature\Editorial;

use App\Enums\PublicationState;
use App\Enums\Role;
use App\Models\CycleReview;
use App\Models\ScholarshipCycle;
use App\Models\SourceEvidence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishDraftsTest extends TestCase
{
    use RefreshDatabase;

    private function draft(bool $withSource = true): ScholarshipCycle
    {
        $cycle = ScholarshipCycle::factory()->create(['publication_state' => PublicationState::Draft]);
        if ($withSource) {
            SourceEvidence::create(['cycle_id' => $cycle->id, 'url' => 'https://example.org/call', 'publisher' => 'Funder', 'publisher_type' => 'funder', 'checked_at' => now(), 'created_by' => null]);
        }

        return $cycle;
    }

    public function test_drafts_are_published_through_the_workflow_with_the_reason_recorded(): void
    {
        $owner = User::factory()->role(Role::SuperAdmin)->create();
        $ok = $this->draft();
        $noSource = $this->draft(false);

        $this->artisan('sas:publish-drafts', ['email' => $owner->email, '--reason' => 'Owner takes responsibility for the source checks.'])->assertFailed(); // one draft has no source

        $this->assertSame(PublicationState::Published, $ok->fresh()->publication_state);
        $this->assertSame(PublicationState::Draft, $noSource->fresh()->publication_state);
        $this->assertDatabaseHas('cycle_reviews', ['cycle_id' => $ok->id, 'to_state' => 'approved', 'override' => true]);
        $this->assertDatabaseHas('audits', ['action' => 'cycle.published']);
        $this->assertStringContainsString('responsibility', (string) CycleReview::where('cycle_id', $ok->id)->where('to_state', 'approved')->value('notes'));
    }

    public function test_it_needs_a_reason_and_a_staff_account_that_may_publish_and_override(): void
    {
        $owner = User::factory()->role(Role::SuperAdmin)->create();
        $editor = User::factory()->role(Role::Editor)->create();
        $this->draft();

        $this->artisan('sas:publish-drafts', ['email' => $owner->email])->assertFailed();
        $this->artisan('sas:publish-drafts', ['email' => $editor->email, '--reason' => 'x'])->assertFailed();
        $this->assertSame(0, ScholarshipCycle::where('publication_state', 'published')->count());
    }
}
