<?php

namespace Tests\Feature\Public;

use App\Domain\Seo\Landing;
use App\Domain\Seo\ScholarshipFacts;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Landing pages and the "at a glance" / "quick answers" text come only from stored scholarship data, and a page exists only with enough of it. */
class LandingPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Cache::flush();
    }

    private function cycle(string $title, string $country, string $degree = 'master', array $attrs = []): ScholarshipCycle
    {
        $cycle = ScholarshipCycle::factory()->published()->create($attrs + ['programme_id' => ScholarshipProgramme::factory()->create(['title' => $title])->id]);
        $cycle->destinations()->sync([Country::where('name', $country)->value('id')]);
        $cycle->degreeLevels()->sync(DegreeLevel::where('code', $degree)->pluck('id'));

        return $cycle;
    }

    public function test_a_destination_page_exists_only_with_three_published_scholarships(): void
    {
        $this->cycle('Alpha Award', 'Japan');
        $this->cycle('Beta Award', 'Japan');
        $this->get('/scholarships/in/japan')->assertNotFound();
        $this->assertNull(app(Landing::class)->forCountry('japan'));

        $this->cycle('Gamma Award', 'Japan');
        Cache::flush();
        $page = $this->get('/scholarships/in/japan')->assertOk()
            ->assertSee('Scholarships in Japan for International Students')
            ->assertSee('SAS lists 3 published scholarships in Japan')
            ->assertSee('Alpha Award')->assertSee('Gamma Award')
            ->assertSee('Quick answers')->assertSee('How many scholarships in Japan does SAS list?');
        $this->assertStringContainsString('"@type":"FAQPage"', $page->getContent());
        $this->assertStringContainsString('"@type":"ItemList"', $page->getContent());
    }

    public function test_a_page_number_past_the_end_is_not_found(): void
    {
        foreach (['A', 'B', 'C'] as $x) {
            $this->cycle($x.' Award', 'Japan');
        }
        Cache::flush();
        $this->get('/scholarships/in/japan')->assertOk();
        $this->get('/scholarships/in/japan?page=1')->assertOk();
        $this->get('/scholarships/in/japan?page=2')->assertNotFound();
    }

    public function test_the_page_lists_only_published_scholarships_and_a_draft_does_not_count(): void
    {
        $this->cycle('Alpha Award', 'Japan');
        $this->cycle('Beta Award', 'Japan');
        $draft = $this->cycle('Hidden Award', 'Japan');
        $draft->forceFill(['publication_state' => 'draft'])->save();
        $this->assertNull(app(Landing::class)->forCountry('japan'));
        $this->get('/scholarships/in/japan')->assertNotFound();
    }

    public function test_degree_and_funding_pages_follow_the_stored_data(): void
    {
        foreach (['A', 'B', 'C'] as $x) {
            $this->cycle($x.' Masters Award', 'Germany', 'master', ['funding_type' => 'full', 'fully_funded_verified' => true]);
        }
        $this->cycle('Partial Award', 'Germany', 'master', ['funding_type' => 'partial']);
        $this->cycle('PhD Award', 'Germany', 'doctorate');
        Cache::flush();

        $this->get('/scholarships/for/masters')->assertOk()->assertSee("Master's Scholarships for International Students")->assertSee('SAS lists 4 published');
        $this->get('/scholarships/for/phd')->assertNotFound(); // one PhD scholarship is not enough for a page
        $this->get('/scholarships/fully-funded')->assertOk()->assertSee('Fully Funded Scholarships for International Students')->assertSee('A Masters Award')->assertDontSee('Partial Award');
        $this->get('/scholarships/partial-funding')->assertNotFound();
    }

    public function test_landing_pages_are_in_the_sitemap_and_the_footer_only_while_they_exist(): void
    {
        foreach (['A', 'B', 'C'] as $x) {
            $this->cycle($x.' Award', 'China');
        }
        Cache::flush();
        $this->assertStringContainsString('/scholarships/in/china', $this->sitemapXml('scholarships'));
        $this->get('/about')->assertSee('Popular scholarship searches')->assertSee('/scholarships/in/china', false);
        $this->get('/site-map')->assertSee('Scholarships in China');

        ScholarshipCycle::query()->first()->forceFill(['publication_state' => 'draft'])->save(); // the listeners clear the caches
        $this->assertStringNotContainsString('/scholarships/in/china', $this->sitemapXml('scholarships'));
        $this->get('/scholarships/in/china')->assertNotFound();
    }

    public function test_the_scholarship_page_gets_an_at_a_glance_paragraph_and_quick_answers_from_its_data(): void
    {
        $cycle = $this->cycle('Glance Award', 'China', 'master', ['funding_type' => 'full', 'fully_funded_verified' => true, 'any_nationality' => true]);
        $cycle->load('programme.funder', 'destinations', 'degreeLevels', 'nationalities', 'excludedNationalities', 'subjects', 'requirements', 'evidence', 'currentDeadlines');

        $facts = app(ScholarshipFacts::class);
        $glance = $facts->glance($cycle);
        $this->assertStringContainsString('Glance Award is a fully funded scholarship', $glance);
        $this->assertStringContainsString('for study in China', $glance);
        $this->assertStringContainsString("at Master's level", $glance);
        $this->assertStringContainsString('Open to students of any nationality', $glance);
        $this->assertStringNotContainsString('Not confirmed', $glance);

        foreach ($facts->faq($cycle) as [$q, $a]) {
            $this->assertStringNotContainsString('Not confirmed', $a);
            $this->assertNotSame('', trim($a));
        }
        $this->assertContains('Is Glance Award fully funded?', array_column($facts->faq($cycle), 0));

        $url = route('scholarships.show', [$cycle->programme->slug, $cycle->slug]);
        $page = $this->get($url)->assertOk()->assertSee('At a glance')->assertSee('Quick answers')->assertSee('Glance Award is a fully funded scholarship');
        $this->assertStringContainsString('"@type":"FAQPage"', $page->getContent());
    }

    public function test_funding_that_is_not_verified_is_not_called_fully_funded(): void
    {
        $cycle = $this->cycle('Unverified Award', 'China', 'master', ['funding_type' => 'full', 'fully_funded_verified' => false]);
        $cycle->load('programme.funder', 'destinations', 'degreeLevels', 'nationalities', 'excludedNationalities', 'subjects', 'requirements', 'evidence', 'currentDeadlines');
        $facts = app(ScholarshipFacts::class);

        $this->assertStringNotContainsString('fully funded scholarship', $facts->glance($cycle));
        $answers = collect($facts->faq($cycle))->firstWhere(0, 'Is Unverified Award fully funded?');
        $this->assertStringContainsString('has not verified', $answers[1]);
    }
}
