<?php

namespace Tests\Feature\Public;

use App\Domain\Scholarship\SearchIndexer;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\University;
use App\Models\UniversityProgramme;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** The Chinese Government Scholarship calls carry every programme that the Study in China system lists as open to CGS applicants. */
class CscCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Cache::flush();
    }

    private function cycle(string $title): ScholarshipCycle
    {
        $cycle = ScholarshipCycle::factory()->published()->create(['programme_id' => ScholarshipProgramme::factory()->create(['title' => $title])->id]);
        $cycle->destinations()->sync([Country::where('slug', 'china')->value('id')]);
        $cycle->degreeLevels()->sync(DegreeLevel::where('code', 'master')->pluck('id'));

        return $cycle;
    }

    private function programme(string $name, string $level = 'master', bool $csc = true, bool $published = true): UniversityProgramme
    {
        $uni = University::first() ?? University::create(['name' => 'Test University', 'slug' => 'test-university', 'csc_key' => 'TST', 'province' => 'Beijing', 'city' => 'Beijing', 'country_id' => Country::where('slug', 'china')->value('id'), 'catalogue_checked_at' => '2026-10-22']);

        return UniversityProgramme::create(['university_id' => $uni->id, 'level' => $level, 'name_en' => $name, 'name_zh' => $name, 'csc_eligible' => $csc, 'published' => $published, 'teaching_language' => 'en']);
    }

    public function test_a_cgs_call_lists_and_searches_the_catalogue(): void
    {
        $cycle = $this->cycle('Chinese Government Scholarship (CSC) Type A');
        $this->programme('Software Engineering');
        $this->programme('Software Testing', 'bachelor');
        $this->programme('Nursing');
        $url = route('scholarships.majors', [$cycle->programme->slug, $cycle->slug]);

        $this->get($url)->assertOk()->assertSee('3 programmes and majors are listed')->assertSee('Programmes open to Chinese Government Scholarship applicants')
            ->assertSee('Software Engineering')->assertSee('Nursing');
        $this->get($url.'?q=software&level=master')->assertOk()->assertSee('1 match for “software”')->assertSee('Software Engineering')->assertDontSee('Software Testing')->assertDontSee('Nursing');
        $this->get($url.'?q=zzzz')->assertOk()->assertSee('No programme in the Study in China list matches');
        $this->get($url.'?cp=9')->assertNotFound();
    }

    public function test_programmes_not_listed_as_open_to_cgs_or_not_published_are_left_out(): void
    {
        $cycle = $this->cycle('Chinese Government Scholarship (CSC) Type A');
        $this->programme('Software Engineering');
        $this->programme('Software Hidden', 'master', true, false);
        $this->programme('Software Self Funded', 'master', false);

        $this->get(route('scholarships.majors', [$cycle->programme->slug, $cycle->slug, 'q' => 'software']))->assertOk()
            ->assertSee('1 match')->assertDontSee('Software Hidden')->assertDontSee('Software Self Funded');
    }

    public function test_other_scholarships_do_not_show_the_catalogue(): void
    {
        $other = $this->cycle('Alpha Award');
        $this->programme('Software Engineering');
        $this->get(route('scholarships.majors', [$other->programme->slug, $other->slug]))->assertNotFound();
        $this->get(route('scholarships.show', [$other->programme->slug, $other->slug]))->assertOk()->assertDontSee('Study in China system');
    }

    public function test_the_scholarship_page_of_a_cgs_call_gives_the_counts_and_links_per_level(): void
    {
        $cycle = $this->cycle('Chinese Government Scholarship (CSC) Type A');
        $this->programme('Software Engineering');
        $this->programme('Law', 'bachelor');

        $this->get(route('scholarships.show', [$cycle->programme->slug, $cycle->slug]))->assertOk()->assertSee('2 programmes at 1 universities')
            ->assertSee(route('scholarships.majors', [$cycle->programme->slug, $cycle->slug, 'level' => 'master']), false);
    }

    public function test_the_scholarship_search_finds_cgs_calls_by_a_major_in_the_catalogue(): void
    {
        $cgs = $this->cycle('Chinese Government Scholarship (CSC) Type A');
        $this->cycle('Alpha Award');
        $this->cycle('Chinese Government Marine Scholarship');
        $this->programme('Software Engineering');
        app(SearchIndexer::class)->reindexAll();

        $this->get(route('scholarships.index', ['q' => 'software engineering']))->assertOk()->assertSee('Type A')->assertDontSee('Alpha Award')->assertDontSee('Marine Scholarship');
        $this->get(route('scholarships.index', ['q' => 'zzzz']))->assertOk()->assertDontSee('Type A');
        $this->assertStringContainsString(route('scholarships.majors', [$cgs->programme->slug, $cgs->slug]), $this->sitemapXml('scholarships'));
    }
}
