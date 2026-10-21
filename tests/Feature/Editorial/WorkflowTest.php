<?php

namespace Tests\Feature\Editorial;

use App\Domain\Editorial\CycleWorkflow;
use App\Enums\PublicationState;
use App\Enums\Role;
use App\Models\ScholarshipCycle;
use App\Models\SourceEvidence;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function draft(User $editor, string $publisherType = 'funder'): ScholarshipCycle
    {
        $cycle = ScholarshipCycle::factory()->create(['created_by' => $editor->id]);
        SourceEvidence::create(['cycle_id' => $cycle->id, 'url' => 'https://example.org/call', 'publisher' => 'Example Funder',
            'publisher_type' => $publisherType, 'checked_at' => now(), 'created_by' => $editor->id]);

        return $cycle;
    }

    public function test_full_happy_path_draft_to_published(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $reviewer = User::factory()->role(Role::Reviewer)->create();
        $wf = app(CycleWorkflow::class);
        $cycle = $this->draft($editor);

        $wf->submitForReview($cycle, $editor);
        $wf->approve($cycle->fresh(), $reviewer);
        $published = $wf->publish($cycle->fresh(), $reviewer);

        $this->assertSame(PublicationState::Published, $published->publication_state);
        $this->assertNotNull($published->last_verified_at);
        $this->assertSame($reviewer->id, $published->verified_by);
        $this->assertNotNull($published->evidence_id);
        $this->assertNotNull($published->review_due_at); // DATA-04
        $this->assertDatabaseCount('cycle_reviews', 3);
    }

    public function test_editor_cannot_approve_or_publish_their_own_draft(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $wf = app(CycleWorkflow::class);
        $cycle = $this->draft($editor);
        $wf->submitForReview($cycle, $editor);

        $this->expectException(AuthorizationException::class);
        $wf->approve($cycle->fresh(), $editor);
    }

    public function test_reviewer_cannot_approve_a_cycle_they_created(): void
    {
        $reviewer = User::factory()->role(Role::Reviewer)->create();
        $wf = app(CycleWorkflow::class);
        $cycle = $this->draft($reviewer);
        $wf->submitForReview($cycle, $reviewer);

        $this->expectException(AuthorizationException::class);
        $wf->approve($cycle->fresh(), $reviewer);
    }

    public function test_administrator_override_needs_a_reason_and_is_recorded(): void
    {
        $admin = User::factory()->role(Role::Administrator)->create();
        $wf = app(CycleWorkflow::class);
        $cycle = $this->draft($admin);
        $wf->submitForReview($cycle, $admin);

        try {
            $wf->approve($cycle->fresh(), $admin);
            $this->fail('Override without reason must be refused');
        } catch (AuthorizationException) {
        }

        $wf->approve($cycle->fresh(), $admin, null, 'Reviewer unavailable, source checked twice');
        $this->assertDatabaseHas('cycle_reviews', ['cycle_id' => $cycle->id, 'to_state' => 'approved', 'override' => true]);
    }

    public function test_aggregator_only_evidence_cannot_be_approved(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $reviewer = User::factory()->role(Role::Reviewer)->create();
        $wf = app(CycleWorkflow::class);
        $cycle = $this->draft($editor, 'aggregator');
        $wf->submitForReview($cycle, $editor);

        $this->expectException(ValidationException::class);
        $wf->approve($cycle->fresh(), $reviewer);
    }

    public function test_submitting_without_any_source_is_refused(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $cycle = ScholarshipCycle::factory()->create(['created_by' => $editor->id]);
        $this->expectException(ValidationException::class);
        app(CycleWorkflow::class)->submitForReview($cycle, $editor);
    }

    public function test_publish_requires_the_publish_permission_and_an_approved_cycle(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $cycle = $this->draft($editor);
        app(CycleWorkflow::class)->submitForReview($cycle, $editor);

        $this->expectException(ValidationException::class); // in review, not approved
        app(CycleWorkflow::class)->publish($cycle->fresh(), User::factory()->role(Role::Reviewer)->create());
    }

    public function test_editor_has_no_publish_permission(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $reviewer = User::factory()->role(Role::Reviewer)->create();
        $wf = app(CycleWorkflow::class);
        $cycle = $this->draft($editor);
        $wf->submitForReview($cycle, $editor);
        $wf->approve($cycle->fresh(), $reviewer);

        $this->expectException(AuthorizationException::class);
        $wf->publish($cycle->fresh(), $editor);
    }

    public function test_request_changes_needs_notes_and_returns_to_the_editor(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $reviewer = User::factory()->role(Role::Reviewer)->create();
        $wf = app(CycleWorkflow::class);
        $cycle = $this->draft($editor);
        $wf->submitForReview($cycle, $editor);

        try {
            $wf->requestChanges($cycle->fresh(), $reviewer, ' ');
            $this->fail('notes required');
        } catch (ValidationException) {
        }
        $out = $wf->requestChanges($cycle->fresh(), $reviewer, 'Closing time unclear');
        $this->assertSame(PublicationState::ChangesRequested, $out->publication_state);
        $wf->submitForReview($out->fresh(), $editor); // can be resubmitted
        $this->assertSame(PublicationState::InReview, $cycle->fresh()->publication_state);
    }

    public function test_dotted_permissions_resolve_through_the_gate_and_deny_unknown(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $this->assertTrue($editor->can('content.draft'));
        $this->assertFalse($editor->can('content.publish'));
        $this->assertFalse($editor->can('totally.unknown'));
        $this->assertFalse(User::factory()->suspended()->role(Role::SuperAdmin)->create()->can('content.publish'));
    }
}
