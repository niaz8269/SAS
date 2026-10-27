<?php

namespace Tests\Feature\Counselling;

use App\Domain\Counselling\Counselling;
use App\Enums\Role;
use App\Filament\Resources\ApplicationReviews\Pages\ListApplicationReviews;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\ApplicationPlan;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

/** Staff see every student with the scholarships they applied for, and a student's page lists them scholarship by scholarship. */
class StudentApplicationsOverviewTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function apply(User $student, array $attrs = []): ApplicationPlan
    {
        $cycle = $this->publishedCycle($attrs, $this->exactDeadline('+60 days'));
        $this->actingAs($student)->post('/student/applications', ['cycle_id' => $cycle->id])->assertRedirect();

        return ApplicationPlan::where('user_id', $student->id)->where('cycle_id', $cycle->id)->firstOrFail();
    }

    public function test_the_student_list_counts_applications_and_filters_students_who_have_some(): void
    {
        $counsellor = User::factory()->role(Role::Advisor)->create();
        $busy = User::factory()->create(['name' => 'Busy Student']);
        $idle = User::factory()->create(['name' => 'Idle Student']);
        foreach ([$busy, $idle] as $s) {
            app(Counselling::class)->assign($s, $counsellor);
        }
        $this->apply($busy);
        $this->apply($busy);

        $this->actingAs($counsellor);
        Livewire::test(ListStudents::class)->assertCanSeeTableRecords([$busy, $idle])
            ->assertTableColumnStateSet('application_plans_count', 2, Student::find($busy->id))
            ->assertTableColumnStateSet('application_plans_count', 0, Student::find($idle->id))
            ->filterTable('has_applications')->assertCanSeeTableRecords([$busy])->assertCanNotSeeTableRecords([$idle]);
    }

    public function test_the_student_page_lists_every_scholarship_applied_for_with_its_status(): void
    {
        $counsellor = User::factory()->role(Role::Advisor)->create();
        $student = User::factory()->create(['name' => 'Amina Khan']);
        app(Counselling::class)->assign($student, $counsellor);
        $one = $this->apply($student);
        $two = $this->apply($student);

        $this->actingAs($counsellor);
        $page = $this->get('/admin/students/'.$student->id)->assertOk()->assertSee('Applications');
        foreach ([$one, $two] as $plan) {
            $page->assertSee($plan->cycle->displayName(), false);
        }
        $page->assertSee('Unfinished');
    }

    public function test_the_review_list_can_be_grouped_by_student_and_opens_the_student(): void
    {
        $admin = User::factory()->role(Role::Administrator)->create();
        $student = User::factory()->create(['name' => 'Grouped Student']);
        $plan = $this->apply($student);
        $plan->forceFill(['stage' => 'submitted', 'review_requested_at' => now()])->save();

        $this->actingAs($admin);
        Livewire::test(ListApplicationReviews::class)->assertCanSeeTableRecords([$plan])
            ->set('tableGrouping', 'user_id')
            ->assertSee('Grouped Student')
            ->assertTableActionVisible('student', $plan);
    }
}
