<?php

namespace Tests\Feature\Student;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\StudentWorkspace\ApplicationReview;
use App\Domain\StudentWorkspace\PlanService;
use App\Enums\Role;
use App\Models\DegreeLevel;
use App\Models\DocumentKind;
use App\Models\DocumentRule;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The student sees one set of four steps and one status wording on Home, in the menu, in the list and in an application. */
class ApplyProgressTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Storage::fake('private_documents');
        Notification::fake();
        config(['sas.uploads.disk' => 'private_documents', 'sas.staff_notification_email' => 'staff@example.test']);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function readyPlan(): array
    {
        $cycle = ScholarshipCycle::factory()->published()->create(['programme_id' => ScholarshipProgramme::factory()->create(['title' => 'Progress Award'])->id]);
        $cycle->degreeLevels()->sync(DegreeLevel::where('code', 'master')->pluck('id'));
        foreach (['photo', 'degree_certificate', 'transcript'] as $key) {
            DocumentRule::create(['kind_id' => DocumentKind::where('key', $key)->value('id'), 'programme_id' => $cycle->programme_id, 'requirement' => 'optional']);
        }
        DocumentRule::create(['kind_id' => DocumentKind::where('key', 'passport')->value('id'), 'programme_id' => $cycle->programme_id, 'requirement' => 'required']);

        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $cycle);
        $forms = app(ApplicationFormService::class);
        $answers = [];
        foreach ($forms->sections() as $section) {
            foreach ($section['fields'] as $name => $def) {
                if ($def['required'] ?? false) {
                    $answers[$name] = match ($def[1]) {
                        'date' => '2000-01-01', 'email' => 'a@example.test', 'select' => $def['options'][0], default => 'x'
                    };
                }
            }
        }
        $answers['other_education'] = [['level' => 'Senior high school', 'institute' => 'Another School', 'from' => '2015-09-01', 'to' => '2019-06-30']];
        $forms->formFor($student)->forceFill(['data' => $answers + ['_declared_at' => now()->toDateTimeString()]])->save();
        $this->actingAs($student)->post('/student/documents', ['files' => [UploadedFile::fake()->createWithContent('f.pdf', self::PDF)], 'type' => 'passport', 'acknowledge' => '1'])->assertRedirect();

        return [$student, $plan];
    }

    private function staff(): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role(Role::Administrator)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['a-b']])->save();

        return $user;
    }

    public function test_home_has_four_steps_and_is_not_all_set_until_the_application_is_sent(): void
    {
        [$student, $plan] = $this->readyPlan();

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()
            ->assertSee('Applying takes four steps')->assertSee('Step 4: Send to SAS')->assertSee('Send your application to SAS')
            ->assertSee('1 not sent yet')->assertSee('Not sent yet')->assertDontSee('You are all set');

        app(ApplicationReview::class)->submit($plan, $student);

        $this->get(route('student.dashboard'))->assertOk()
            ->assertSee('You are all set')->assertSee('All sent to SAS')->assertSee('With SAS')->assertDontSee('Send your application to SAS');
    }

    public function test_a_returned_application_is_on_home_with_the_notes_and_the_same_status_everywhere(): void
    {
        [$student, $plan] = $this->readyPlan();
        app(ApplicationReview::class)->submit($plan, $student);
        app(ApplicationReview::class)->review($plan->refresh(), 'return', 'The passport scan is blurred.', $this->staff());

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()
            ->assertSee('SAS sent an application back to you')->assertSee('The passport scan is blurred.')->assertSee('Fix it and send again')->assertSee('Needs changes')
            ->assertDontSee('You are all set');
        $this->get(route('student.applications.index'))->assertOk()->assertSee('Needs changes')->assertSee('Fix and send again')->assertSee('The passport scan is blurred.');
        $this->get(route('student.applications.show', $plan))->assertOk()->assertSee('Needs changes')->assertSee('SAS returned this application');
    }

    public function test_application_pages_use_the_student_menu_with_the_same_four_steps(): void
    {
        [$student, $plan] = $this->readyPlan();

        foreach ([route('student.applications.materials', $plan), route('student.applications.show', $plan), route('student.applications.index')] as $url) {
            $this->actingAs($student)->get($url)->assertOk()->assertSee('How to apply')->assertSee('Choose scholarships')->assertSee('Send to SAS')->assertSee('My applications');
        }
    }
}
