<?php

namespace Tests\Feature\Public;

use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

/** ANA-01: the funnel is recorded without personal data or free text. */
class AnalyticsTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    public function test_save_and_plan_are_recorded_once_each_with_no_personal_data(): void
    {
        $user = User::factory()->create(['email' => 'amina@example.test']);
        $cycle = $this->publishedCycle();
        $this->actingAs($user);

        $this->post('/student/saved-scholarships', ['cycle_id' => $cycle->id]);
        $this->post('/student/saved-scholarships', ['cycle_id' => $cycle->id]); // repeat: not counted again
        $this->post('/student/applications', ['cycle_id' => $cycle->id]);
        $this->post('/student/applications', ['cycle_id' => $cycle->id]);

        $this->assertSame(1, AnalyticsEvent::where('name', 'scholarship_saved')->where('cycle_id', $cycle->id)->count());
        $this->assertSame(1, AnalyticsEvent::where('name', 'plan_created')->where('cycle_id', $cycle->id)->count());
        $this->assertStringNotContainsString('amina', json_encode(AnalyticsEvent::all()));
    }

    public function test_enquiries_and_consultations_record_category_only(): void
    {
        Mail::fake();
        $this->post('/enquiries', ['category' => 'visa', 'name' => 'Secret Name', 'email' => 'private@example.test', 'message' => 'My passport number is 12345678',
            'contact_preference' => 'email', 'consent' => '1']);
        $this->post('/consultations', ['name' => 'Secret Name', 'email' => 'private@example.test', 'preferred_from' => now()->addDay()->toDateString(),
            'student_timezone' => 'UTC', 'contact_method' => 'email', 'consent' => '1']);

        $this->assertSame(['category' => 'visa', 'service' => false], AnalyticsEvent::where('name', 'enquiry_submitted')->first()->meta);
        $this->assertSame(1, AnalyticsEvent::where('name', 'consultation_requested')->count());
        $dump = json_encode(AnalyticsEvent::all());
        foreach (['Secret', 'private@', 'passport', '12345678'] as $needle) {
            $this->assertStringNotContainsString($needle, $dump);
        }
    }
}
