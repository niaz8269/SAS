<?php

namespace Tests\Feature\Public;

use App\Domain\Scholarship\SearchIndexer;
use App\Models\Country;
use App\Models\CycleOffering;
use App\Models\DegreeLevel;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\University;
use App\Models\UniversityProgramme;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** A subject search must reach scholarships: in the scholarship search (programmes each scholarship offers are indexed) and on the programmes page. */
class ScholarshipsForMajorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Cache::flush();
    }

    private function cycle(string $title, array $attrs = []): ScholarshipCycle
    {
        $cycle = ScholarshipCycle::factory()->published()->create($attrs + ['programme_id' => ScholarshipProgramme::factory()->create(['title' => $title])->id]);
        $cycle->destinations()->sync([Country::where('slug', 'china')->value('id')]);
        $cycle->degreeLevels()->sync(DegreeLevel::where('code', 'master')->pluck('id'));

        return $cycle;
    }

    private function programme(string $name, bool $csc = true): UniversityProgramme
    {
        $uni = University::first() ?? University::create(['name' => 'Test University', 'slug' => 'test-university', 'csc_key' => 'TST', 'province' => 'Beijing', 'city' => 'Beijing', 'country_id' => Country::where('slug', 'china')->value('id'), 'catalogue_checked_at' => '2026-10-22']);

        return UniversityProgramme::create(['university_id' => $uni->id, 'level' => 'master', 'name_en' => $name, 'name_zh' => $name, 'csc_eligible' => $csc, 'published' => true, 'teaching_language' => 'en']);
    }

    public function test_the_scholarship_search_finds_a_major_that_only_a_programme_line_names(): void
    {
        $cycle = $this->cycle('Alpha Award');
        CycleOffering::create(['cycle_id' => $cycle->id, 'degree_level_id' => DegreeLevel::where('code', 'master')->value('id'), 'name' => 'Marine Biotechnology', 'institution' => 'Ocean University']);
        $this->cycle('Beta Award');
        app(SearchIndexer::class)->reindexAll();

        $this->get(route('scholarships.index', ['q' => 'marine biotechnology']))->assertOk()->assertSee('Alpha Award')->assertDontSee('Beta Award');
    }

    public function test_the_programmes_page_shows_the_scholarships_for_the_subject_searched(): void
    {
        $named = $this->cycle('Named Award');
        CycleOffering::create(['cycle_id' => $named->id, 'degree_level_id' => DegreeLevel::where('code', 'master')->value('id'), 'name' => 'Marine Biotechnology']);
        $this->cycle('Any Subject Award', ['any_subject' => true]);
        $this->cycle('Other Award'); // names no subject and does not fund any subject
        $this->programme('Marine Biotechnology');
        app(SearchIndexer::class)->reindexAll();

        $this->get(route('programmes.index', ['q' => 'marine biotechnology', 'level' => 'master']))->assertOk()
            ->assertSee('Scholarships for “marine biotechnology”')->assertSee('Named Award')
            ->assertSee('Scholarships that fund any subject')->assertSee('Any Subject Award')->assertDontSee('Other Award');
    }

    public function test_nothing_is_shown_when_no_scholarship_applies_and_no_search_was_made(): void
    {
        $this->cycle('Other Award');
        $this->programme('Marine Biotechnology');
        app(SearchIndexer::class)->reindexAll();

        $this->get(route('programmes.index', ['q' => 'marine biotechnology']))->assertOk()->assertDontSee('Scholarships for “marine');
        $this->get(route('programmes.index'))->assertOk()->assertDontSee('major-scholarships');
    }
}
