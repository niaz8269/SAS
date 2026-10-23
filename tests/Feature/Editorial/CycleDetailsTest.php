<?php

namespace Tests\Feature\Editorial;

use App\Models\Country;
use App\Models\CycleMilestone;
use App\Models\ScholarshipCycle;
use App\Models\University;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class CycleDetailsTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    public function test_timeline_shows_every_stage_and_says_not_announced_when_the_source_gives_no_date(): void
    {
        $cycle = $this->publishedCycle([], $this->exactDeadline());
        CycleMilestone::create(['cycle_id' => $cycle->id, 'kind' => 'opens', 'date_type' => 'date_only', 'starts_on' => '2026-09-01', 'source_text' => 'Applications open on 1 September 2026']);
        CycleMilestone::create(['cycle_id' => $cycle->id, 'kind' => 'interview', 'date_type' => 'range', 'starts_on' => '2027-01-10', 'ends_on' => '2027-02-20']);
        CycleMilestone::create(['cycle_id' => $cycle->id, 'kind' => 'other', 'label' => 'Scholarship starts', 'date_type' => 'month', 'starts_on' => '2027-09-01']);

        $this->get(route('scholarships.show', [$cycle->programme->slug, $cycle->slug]))->assertOk()
            ->assertSee('Applications open')->assertSee('1 September 2026')->assertSee('Applications open on 1 September 2026')
            ->assertSee('Application deadline')->assertSee('Interviews and tests')->assertSee('10 January 2027 to 20 February 2027')
            ->assertSee('Final results')->assertSee('Not announced yet')->assertSee('Scholarship starts')->assertSee('September 2027');
    }

    public function test_a_dated_milestone_without_a_date_is_shown_as_not_announced_never_as_a_guess(): void
    {
        $m = new CycleMilestone(['date_type' => 'exact', 'starts_on' => null]);
        $this->assertSame('Not announced', explode(',', $m->display())[0]);
        $this->assertSame('Depends on your country or route', (new CycleMilestone(['date_type' => 'route_dependent']))->display());
        $this->assertSame('Not announced yet', (new CycleMilestone(['date_type' => 'not_announced']))->display());
        $this->assertSame('mid-October 2026 to January 2027', (new CycleMilestone(['date_type' => 'range', 'starts_on' => '2026-10-15', 'ends_on' => '2027-01-31', 'time_text' => 'mid-October 2026 to January 2027']))->display());
        $this->assertSame('31 October 2026, 11:59pm UK time', (new CycleMilestone(['date_type' => 'exact', 'starts_on' => '2026-10-31', 'time_text' => '11:59pm UK time']))->display());
    }

    public function test_universities_and_programmes_are_listed_and_the_apply_portal_never_appears(): void
    {
        $cycle = $this->publishedCycle([], $this->exactDeadline());
        $china = Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china']);
        $uni = University::create(['name' => 'Peking University', 'slug' => 'peking-university', 'csc_key' => 'A', 'country_id' => $china->id]);
        $cycle->universities()->attach($uni->id);
        $cycle->forceFill(['institutions_note' => 'Applicants choose a university from the official list.', 'programmes_note' => 'Master and doctoral programmes in all fields.'])->save();
        $cycle->destinations()->attach($china->id);
        $cycle->programme->update(['title' => 'Chinese Government Scholarship (test)']);

        $this->get(route('scholarships.show', [$cycle->programme->slug, $cycle->slug]))->assertOk()
            ->assertSee('Universities included')->assertSee('Peking University')->assertSee('Applicants choose a university from the official list.')
            ->assertSee('Degrees and programmes offered')->assertSee('Master and doctoral programmes in all fields.')->assertSee('programmes listed as open to Chinese Government Scholarship')
            ->assertDontSee('Apply on official website')->assertDontSee('apply.example.org');
    }

    public function test_the_importer_creates_new_scholarships_as_drafts_and_adds_milestones_and_universities(): void
    {
        $this->seed(TaxonomySeeder::class);
        $file = tempnam(sys_get_temp_dir(), 'cd').'.json';
        file_put_contents($file, json_encode([[
            'external_key' => 'hu-test-2027', 'is_new' => true, 'checked_on' => '2026-10-23',
            'base' => ['programme_title' => 'Test Hungary Scholarship', 'funder_name' => 'Hungarian Government', 'host_country_code' => 'HU', 'cycle_year' => '2027', 'intake_term' => '2027-28',
                'funding_type' => 'unknown', 'funding_summary' => 'Amounts not stated.', 'teaching_language' => 'English', 'degree_codes' => ['master'], 'summary' => 'A test scholarship.',
                'source_url' => 'https://example.gov.hu/page', 'source_publisher' => 'Hungarian Government', 'source_publisher_type' => 'government', 'route_name' => 'Through the sending partner'],
            'deadline' => ['type' => 'not_announced', 'source_text' => 'Not published yet'],
            'milestones' => [['kind' => 'opens', 'date_type' => 'month', 'starts_on' => '2026-11-01', 'source_text' => 'Opens in November 2026'], ['kind' => 'results', 'date_type' => 'exact', 'starts_on' => null]],
            'institutions' => ['note' => 'Any partner university.', 'names' => ['Corvinus University of Budapest'], 'country_code' => 'HU'],
            'programmes_note' => 'Master programmes taught in English.',
        ]]));

        $this->artisan('sas:import-cycle-details', ['files' => [$file]])->assertSuccessful();

        $cycle = ScholarshipCycle::where('external_key', 'hu-test-2027')->firstOrFail();
        $this->assertSame('draft', $cycle->publication_state->value);
        $this->assertSame(2, $cycle->milestones()->count());
        $this->assertSame('not_announced', $cycle->milestones()->where('kind', 'results')->value('date_type')); // dated type without a date is downgraded
        $this->assertSame('Any partner university.', $cycle->institutions_note);
        $this->assertSame(['Corvinus University of Budapest'], $cycle->universities()->pluck('name')->all());

        $this->artisan('sas:import-cycle-details', ['files' => [$file]])->assertSuccessful(); // idempotent
        $this->assertSame(2, $cycle->milestones()->count());
    }
}
