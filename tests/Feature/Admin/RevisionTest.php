<?php

namespace Tests\Feature\Admin;

use App\Domain\Editorial\CycleRevisions;
use App\Enums\Role;
use App\Filament\Resources\ScholarshipCycles\Pages\EditScholarshipCycle;
use App\Models\Audit;
use App\Models\Country;
use App\Models\CycleRequirement;
use App\Models\CycleRevision;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

/** Edits to a live listing are staged and need a second person's approval before the public sees them. */
class RevisionTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function staff(Role|string $role): User
    {
        $u = User::factory()->role($role)->create();
        $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['a']])->save();
        $this->actingAs($u);

        return $u;
    }

    private function live(): ScholarshipCycle
    {
        $cycle = $this->publishedCycle(['funding_summary' => 'Original summary', 'summary' => 'Original text', 'funding_type' => 'partial']);
        $cycle->destinations()->attach(Country::factory()->create());

        return $cycle->fresh();
    }

    private function form(ScholarshipCycle $cycle, array $over = []): array
    {
        return array_merge([
            'programme_id' => $cycle->programme_id, 'cycle_key' => $cycle->cycle_key, 'slug' => $cycle->slug, 'intake_year' => $cycle->intake_year,
            'funding_type' => $cycle->funding_type, 'funding_summary' => $cycle->funding_summary, 'summary' => $cycle->summary, 'delivery_mode' => 'unknown',
            'destinations' => $cycle->destinations()->pluck('countries.id')->all(),
        ], $over);
    }

    public function test_an_editors_change_to_a_live_cycle_is_staged_not_applied(): void
    {
        $cycle = $this->live();
        $editor = $this->staff('editor');

        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])
            ->fillForm($this->form($cycle, ['funding_summary' => '<p>Changed wording</p>', 'funding_type' => 'full']))->call('save');

        $this->assertSame('Original summary', $cycle->fresh()->funding_summary); // public listing untouched
        $this->assertSame('partial', $cycle->fresh()->funding_type);
        $rev = CycleRevision::firstOrFail();
        $this->assertSame('pending', $rev->status);
        $this->assertSame($editor->id, $rev->proposed_by);
        $this->assertEquals(['funding_summary' => '<p>Changed wording</p>', 'funding_type' => 'full'], $rev->changes['fields']);
        $this->get(route('scholarships.show', [$cycle->programme->slug, $cycle->slug]))->assertSee('Original summary')->assertDontSee('Changed wording');
        $this->assertDatabaseHas('audits', ['action' => 'cycle.revision_proposed', 'actor_id' => $editor->id]);
    }

    public function test_saving_without_changes_stages_nothing(): void
    {
        $cycle = $this->live();
        $this->staff('editor');
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])->fillForm($this->form($cycle))->call('save');
        $this->assertSame(0, CycleRevision::count());
    }

    public function test_a_different_reviewer_approves_and_it_goes_live_with_relations_and_history(): void
    {
        $cycle = $this->live();
        $newCountry = Country::factory()->create();
        $editor = $this->staff('editor');
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])
            ->fillForm($this->form($cycle, ['summary' => 'Corrected text', 'destinations' => [$newCountry->id]]))->call('save');

        $reviewer = $this->staff('reviewer');
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])
            ->assertActionVisible('applyRevision')->callAction('applyRevision', ['notes' => 'Checked against the source']);

        $fresh = $cycle->fresh();
        $this->assertSame('<p>Corrected text</p>', $fresh->summary);
        $this->assertSame([$newCountry->id], $fresh->destinations()->pluck('countries.id')->all());
        $rev = CycleRevision::firstOrFail();
        $this->assertSame('applied', $rev->status);
        $this->assertSame($reviewer->id, $rev->decided_by);
        $this->assertDatabaseHas('audits', ['action' => 'cycle.revision_applied', 'actor_id' => $reviewer->id]);
        $this->assertDatabaseHas('cycle_reviews', ['cycle_id' => $cycle->id, 'reviewer_id' => $reviewer->id]);
        $this->get(route('scholarships.show', [$cycle->programme->slug, $cycle->slug]))->assertSee('Corrected text');
        $this->assertNotNull($editor);
    }

    public function test_a_reviewers_own_proposal_cannot_be_self_approved_without_an_admin_override(): void
    {
        $cycle = $this->live();
        $reviewer = $this->staff('reviewer');
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])->fillForm($this->form($cycle, ['summary' => 'Mine']))->call('save');
        $rev = CycleRevision::firstOrFail();

        $this->expectException(AuthorizationException::class);
        app(CycleRevisions::class)->apply($rev, $reviewer);
    }

    public function test_an_administrator_may_self_approve_only_with_a_recorded_reason(): void
    {
        $cycle = $this->live();
        $admin = $this->staff(Role::Administrator);
        $rev = CycleRevision::create(['cycle_id' => $cycle->id, 'proposed_by' => $admin->id, 'changes' => ['fields' => ['summary' => 'Admin text'], 'relations' => []],
            'base_hash' => app(CycleRevisions::class)->hash($cycle)]);
        $svc = app(CycleRevisions::class);

        try {
            $svc->apply($rev, $admin);
            $this->fail('override needs a reason');
        } catch (AuthorizationException) {
        }
        $svc->apply($rev, $admin, null, 'No reviewer available, source checked twice');
        $this->assertSame('Admin text', $cycle->fresh()->summary);
        $this->assertTrue($rev->fresh()->override);
    }

    public function test_editors_cannot_approve_or_see_the_review_action(): void
    {
        $cycle = $this->live();
        $this->staff('editor');
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])->fillForm($this->form($cycle, ['summary' => 'x']))->call('save');
        $other = $this->staff('editor');
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])->assertActionHidden('applyRevision');
        $this->expectException(AuthorizationException::class);
        app(CycleRevisions::class)->apply(CycleRevision::firstOrFail(), $other);
    }

    public function test_rejecting_needs_a_reason_and_leaves_the_listing_unchanged(): void
    {
        $cycle = $this->live();
        $this->staff('editor');
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])->fillForm($this->form($cycle, ['summary' => 'Bad edit']))->call('save');
        $this->staff('reviewer');

        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])->callAction('rejectRevision', ['notes' => ''])->assertHasActionErrors(['notes']);
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])->callAction('rejectRevision', ['notes' => 'Not what the source says']);
        $this->assertSame('rejected', CycleRevision::firstOrFail()->status);
        $this->assertSame('Original text', $cycle->fresh()->summary);
    }

    public function test_a_proposal_made_against_an_older_live_version_is_marked_outdated_not_applied(): void
    {
        $cycle = $this->live();
        $this->staff('editor');
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])->fillForm($this->form($cycle, ['summary' => 'Stale edit']))->call('save');

        $cycle->forceFill(['summary' => 'Someone else updated it'])->save(); // live moved on
        $reviewer = $this->staff('reviewer');
        try {
            app(CycleRevisions::class)->apply(CycleRevision::firstOrFail(), $reviewer);
            $this->fail('should refuse');
        } catch (ValidationException) {
        }
        $this->assertSame('outdated', CycleRevision::firstOrFail()->status);
        $this->assertSame('Someone else updated it', $cycle->fresh()->summary);
    }

    public function test_a_new_proposal_from_the_same_person_replaces_their_earlier_one(): void
    {
        $cycle = $this->live();
        $this->staff('editor');
        foreach (['First', 'Second'] as $text) {
            Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])->fillForm($this->form($cycle, ['summary' => $text]))->call('save');
        }
        $this->assertSame(1, CycleRevision::where('status', 'pending')->count());
        $this->assertSame(1, CycleRevision::where('status', 'withdrawn')->count());
        $this->assertSame('<p>Second</p>', CycleRevision::where('status', 'pending')->first()->changes['fields']['summary']);
    }

    public function test_non_reviewers_cannot_claim_verification_through_a_proposal(): void
    {
        $cycle = $this->live();
        $this->staff('editor');
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])
            ->fillForm($this->form($cycle, ['funding_type' => 'full', 'fully_funded_verified' => true]))->call('save');
        $this->assertArrayNotHasKey('fully_funded_verified', CycleRevision::firstOrFail()->changes['fields']);
    }

    public function test_draft_cycles_still_save_directly(): void
    {
        $this->staff('editor');
        $draft = ScholarshipCycle::factory()->create(['summary' => 'Draft text', 'created_by' => auth()->id()]);
        Livewire::test(EditScholarshipCycle::class, ['record' => $draft->getKey()])
            ->fillForm($this->form($draft, ['summary' => 'Draft edited']))->call('save');
        $this->assertSame('<p>Draft edited</p>', $draft->fresh()->summary);
        $this->assertSame(0, CycleRevision::count());
    }

    public function test_direct_child_changes_on_live_cycles_are_audited(): void
    {
        $cycle = $this->live();
        $reviewer = $this->staff('reviewer');
        $base = Audit::where('action', 'cycle.child_changed')->count();
        $req = CycleRequirement::create(['cycle_id' => $cycle->id, 'text' => 'Transcript']);
        $req->update(['text' => 'Certified transcript']);
        $this->assertSame($base + 2, Audit::where('action', 'cycle.child_changed')->count());
        $this->assertNotNull($reviewer);

        $draft = ScholarshipCycle::factory()->create();
        CycleRequirement::create(['cycle_id' => $draft->id, 'text' => 'Draft requirement']);
        $this->assertSame($base + 2, Audit::where('action', 'cycle.child_changed')->count()); // drafts are not live
    }
}
