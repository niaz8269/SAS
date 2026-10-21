<?php

namespace Tests\Feature\Student;

use App\Domain\Communication\Preferences;
use App\Models\ApplicationPlan;
use App\Models\CycleRequirement;
use App\Models\NotificationEvent;
use App\Models\SavedScholarship;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    private function planFor(User $user, ?ScholarshipCycle $cycle = null): ApplicationPlan
    {
        $cycle ??= $this->publishedCycle([], $this->exactDeadline('+60 days'));
        $this->actingAs($user)->post('/student/applications', ['cycle_id' => $cycle->id])->assertRedirect();

        return ApplicationPlan::where('user_id', $user->id)->where('cycle_id', $cycle->id)->firstOrFail();
    }

    public function test_saving_is_unique_per_user_and_cycle_and_only_public_cycles_can_be_saved(): void
    {
        $user = User::factory()->create();
        $cycle = $this->publishedCycle();
        $this->actingAs($user)->post('/student/saved-scholarships', ['cycle_id' => $cycle->id])->assertRedirect();
        $this->actingAs($user)->post('/student/saved-scholarships', ['cycle_id' => $cycle->id])->assertRedirect();
        $this->assertSame(1, SavedScholarship::count());

        $draft = ScholarshipCycle::factory()->create();
        $this->actingAs($user)->post('/student/saved-scholarships', ['cycle_id' => $draft->id])->assertNotFound();
        $this->actingAs($user)->post('/student/applications', ['cycle_id' => $draft->id])->assertNotFound();
    }

    public function test_saving_does_not_create_a_plan_and_does_not_turn_on_marketing(): void
    {
        $user = User::factory()->create();
        Preferences::ensureDefaults($user);
        $this->actingAs($user)->post('/student/saved-scholarships', ['cycle_id' => $this->publishedCycle()->id]);
        $this->assertSame(0, ApplicationPlan::count());
        $this->assertFalse(Preferences::enabled($user, 'marketing', 'mail'));
        $this->assertFalse(Preferences::enabled($user, 'marketing', 'in_app'));
    }

    public function test_plan_checklist_is_a_snapshot_of_the_requirements(): void
    {
        $cycle = $this->publishedCycle();
        CycleRequirement::create(['cycle_id' => $cycle->id, 'text' => 'Transcript', 'sort' => 1]);
        CycleRequirement::create(['cycle_id' => $cycle->id, 'text' => 'Passport copy', 'sort' => 2]);
        $plan = $this->planFor(User::factory()->create(), $cycle);

        $this->assertSame(['Transcript', 'Passport copy'], $plan->tasks->pluck('title')->all());
        $this->assertSame('interested', $plan->status->value);
        $this->assertNull($plan->submitted_at);
    }

    public function test_changed_requirements_show_a_prompt_and_never_overwrite_completed_work(): void
    {
        $user = User::factory()->create();
        $cycle = $this->publishedCycle();
        $req = CycleRequirement::create(['cycle_id' => $cycle->id, 'text' => 'Transcript', 'sort' => 1]);
        $plan = $this->planFor($user, $cycle);
        $plan->tasks()->first()->update(['completed_at' => now(), 'notes' => 'my note']);
        $plan->tasks()->create(['title' => 'My own task', 'is_custom' => true]);

        $req->update(['text' => 'Certified transcript']);
        CycleRequirement::create(['cycle_id' => $cycle->id, 'text' => 'New reference letter', 'sort' => 2]);

        $this->get(route('student.applications.show', $plan))->assertOk()->assertSee('Requirements updated');
        $this->post(route('student.applications.acknowledge', $plan))->assertRedirect();

        $tasks = $plan->fresh()->tasks;
        $first = $tasks->firstWhere('requirement_id', $req->id);
        $this->assertNotNull($first->completed_at);               // completed state kept
        $this->assertSame('my note', $first->notes);
        $this->assertTrue($first->requirement_updated);            // flagged
        $this->assertSame('Certified transcript', $first->snapshot_text);
        $this->assertTrue($tasks->contains('title', 'New reference letter'));
        $this->assertTrue($tasks->contains('title', 'My own task'));
        $this->get(route('student.applications.show', $plan))->assertDontSee('The official requirements for this call have changed');
    }

    public function test_submitted_needs_student_confirmation_and_is_labelled_student_reported(): void
    {
        $user = User::factory()->create();
        $plan = $this->planFor($user);

        $this->patch(route('student.applications.update', $plan), ['status' => 'submitted'])->assertSessionHasErrors('confirm_submitted');
        $this->assertSame('interested', $plan->fresh()->status->value);

        $this->patch(route('student.applications.update', $plan), ['status' => 'submitted', 'confirm_submitted' => 1, 'submission_reference' => 'REF-1'])->assertRedirect();
        $plan->refresh();
        $this->assertSame('submitted', $plan->status->value);
        $this->assertSame('student_reported', $plan->status_source);   // never "platform submitted"
        $this->assertNotNull($plan->submitted_at);
        $this->get(route('student.applications.show', $plan))->assertSee('Reported by you');
    }

    public function test_invalid_status_transitions_return_409(): void
    {
        $plan = $this->planFor(User::factory()->create());
        $this->patch(route('student.applications.update', $plan), ['status' => 'awarded'])->assertStatus(409);
        $this->patch(route('student.applications.update', $plan), ['status' => 'withdrawn'])->assertRedirect();
        $this->patch(route('student.applications.update', $plan), ['status' => 'preparing'])->assertStatus(409); // terminal
        $this->patch(route('student.applications.update', $plan), ['status' => 'nonsense'])->assertSessionHasErrors('status');
    }

    public function test_full_status_path_to_awarded(): void
    {
        $plan = $this->planFor(User::factory()->create());
        foreach (['preparing' => [], 'ready_for_review' => [], 'submitted' => ['confirm_submitted' => 1], 'awaiting_outcome' => [], 'awarded' => []] as $s => $extra) {
            $this->patch(route('student.applications.update', $plan), ['status' => $s] + $extra)->assertRedirect();
        }
        $this->assertSame('awarded', $plan->fresh()->status->value);
    }

    public function test_plan_creation_is_idempotent(): void
    {
        $user = User::factory()->create();
        $cycle = $this->publishedCycle();
        $this->planFor($user, $cycle);
        $this->planFor($user, $cycle);
        $this->assertSame(1, ApplicationPlan::count());
    }

    public function test_withdrawing_a_plan_cancels_its_pending_reminders(): void
    {
        $user = User::factory()->create();
        Preferences::ensureDefaults($user);
        $plan = $this->planFor($user);
        $this->assertGreaterThan(0, NotificationEvent::where('status', 'pending')->count());
        $this->patch(route('student.applications.update', $plan), ['status' => 'withdrawn']);
        $this->assertSame(0, NotificationEvent::where('status', 'pending')->count());
    }

    public function test_custom_tasks_can_be_added_completed_and_removed_but_requirement_tasks_cannot_be_deleted(): void
    {
        $cycle = $this->publishedCycle();
        CycleRequirement::create(['cycle_id' => $cycle->id, 'text' => 'Transcript', 'sort' => 1]);
        $plan = $this->planFor(User::factory()->create(), $cycle);

        $this->post(route('student.applications.tasks.store', $plan), ['title' => 'Book IELTS', 'due_at' => now()->addMonth()->toDateString()])->assertRedirect();
        $custom = $plan->tasks()->where('is_custom', true)->first();
        $this->patch(route('student.applications.tasks.update', [$plan, $custom]), ['completed' => 1]);
        $this->assertNotNull($custom->fresh()->completed_at);
        $this->delete(route('student.applications.tasks.destroy', [$plan, $custom]))->assertRedirect();
        $this->assertNull($custom->fresh());

        $req = $plan->tasks()->where('is_custom', false)->first();
        $this->delete(route('student.applications.tasks.destroy', [$plan, $req]))->assertNotFound();
    }

    public function test_profile_keeps_grades_verbatim_and_everything_is_optional(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/student/profile', ['name' => $user->name, 'timezone' => 'UTC'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->put('/student/profile', [
            'name' => $user->name, 'timezone' => 'UTC', 'gpa_value' => '3.4', 'gpa_scale' => 'CGPA /4.0',
            'language_evidence' => [['test' => 'IELTS', 'score' => '6.5'], ['test' => '', 'score' => '']],
        ])->assertRedirect();
        $profile = $user->fresh()->studentProfile;
        $this->assertSame('3.4', $profile->gpa_value);
        $this->assertSame('CGPA /4.0', $profile->gpa_scale);
        $this->assertSame([['test' => 'IELTS', 'score' => '6.5']], $profile->language_evidence);
        $this->get('/student')->assertOk();
    }

    public function test_detail_page_shows_the_students_match_with_explanations_and_no_probability(): void
    {
        $user = User::factory()->create();
        $cycle = $this->publishedCycle();
        $user->studentProfile()->create([]);
        $this->actingAs($user)->get(route('scholarships.show', [$cycle->programme->slug, $cycle->slug]))
            ->assertOk()->assertSee('More information needed')->assertSee('not a prediction')->assertDontSee('chance')->assertDontSee('probability');
    }
}
