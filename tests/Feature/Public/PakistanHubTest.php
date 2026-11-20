<?php

namespace Tests\Feature\Public;

use App\Domain\Seo\PakistanHub;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Scholarships for Pakistani students: who is listed, which facet pages exist, and the plain-text summary for AI assistants. */
class PakistanHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Cache::flush();
    }

    private function cycle(string $title, string $destination, string $degree = 'master', array $attrs = [], array $nationalities = [], array $excluded = []): ScholarshipCycle
    {
        $cycle = ScholarshipCycle::factory()->published()->create($attrs + ['programme_id' => ScholarshipProgramme::factory()->create(['title' => $title])->id]);
        $cycle->destinations()->sync([Country::where('name', $destination)->value('id')]);
        $cycle->degreeLevels()->sync(DegreeLevel::where('code', $degree)->pluck('id'));
        $cycle->nationalities()->sync(Country::whereIn('code', $nationalities)->pluck('id'));
        $cycle->excludedNationalities()->sync(Country::whereIn('code', $excluded)->pluck('id'));

        return $cycle;
    }

    public function test_the_hub_lists_scholarships_that_name_pakistan_or_are_open_to_all_and_leaves_out_the_rest(): void
    {
        $this->cycle('Named Award', 'China', 'master', [], ['PK']);
        $this->cycle('Open Award', 'China', 'doctorate', ['any_nationality' => true]);
        $this->cycle('Third Award', 'China', 'master', ['any_nationality' => true]);
        $this->cycle('Excludes Pakistan Award', 'China', 'master', ['any_nationality' => true], [], ['PK']);
        $this->cycle('Only Nigeria Award', 'China', 'master', [], ['NG']);

        $page = $this->get('/scholarships-for-pakistani-students')->assertOk()
            ->assertSee('Scholarships for Pakistani Students '.PakistanHub::year())
            ->assertSee('SAS lists 3 published scholarships for Pakistani students')
            ->assertSee('Named Award')->assertSee('Open Award')->assertSee('Third Award')
            ->assertDontSee('Excludes Pakistan Award')->assertDontSee('Only Nigeria Award')
            ->assertSee('Applying from Pakistan')->assertSee('Higher Education Commission (HEC)')
            ->assertSee('Quick answers');
        $this->assertStringContainsString('"@type":"FAQPage"', $page->getContent());

        // three in China: a China page; two master's only: no MS page (fewer than three)
        $this->get('/scholarships-for-pakistani-students/in-china')->assertOk()->assertSee('Scholarships for Pakistani Students in China');
        $this->get('/scholarships-for-pakistani-students/ms')->assertNotFound();
        $this->get('/scholarships-for-pakistani-students/in-japan')->assertNotFound();
    }

    public function test_the_old_nationality_address_redirects_and_there_is_no_hub_with_too_few_scholarships(): void
    {
        $this->get('/scholarships/from/pakistan')->assertRedirect('/scholarships-for-pakistani-students')->assertStatus(301);
        $this->cycle('Lonely Award', 'China', 'master', [], ['PK']);
        $this->get('/scholarships-for-pakistani-students')->assertNotFound();
    }

    public function test_llms_txt_describes_the_site_and_lists_the_pakistan_pages(): void
    {
        foreach (['A', 'B', 'C'] as $x) {
            $this->cycle($x.' Award', 'United Kingdom', 'master', ['any_nationality' => true]);
        }

        $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('# '.config('sas.brand.name'), false)
            ->assertSee('## For students from Pakistan', false)
            ->assertSee('/scholarships-for-pakistani-students', false)
            ->assertSee('Scholarships for Pakistani Students in the UK', false);
    }

    public function test_the_indexnow_key_file_matches_the_configured_key_and_a_local_run_sends_nothing(): void
    {
        $key = config('sas.indexnow_key');
        $this->assertSame($key, trim((string) file_get_contents(public_path($key.'.txt'))));
        $this->artisan('sas:indexnow', ['--all' => true])->assertSuccessful()->expectsOutputToContain('Not the live site');
    }
}
