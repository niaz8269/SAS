<?php

namespace Tests\Feature\Public;

use App\Models\CycleOffering;
use App\Models\DegreeLevel;
use App\Models\ScholarshipCycle;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** A scholarship that names many majors: the page shows the first 30 per level, a full searchable list is one click away and is in the sitemap. */
class ScholarshipMajorsPageTest extends TestCase
{
    use RefreshDatabase;

    private function cycleWithMajors(int $n): ScholarshipCycle
    {
        $this->seed(TaxonomySeeder::class);
        Cache::flush();
        $cycle = ScholarshipCycle::factory()->published()->create();
        $master = DegreeLevel::where('code', 'master')->first();
        $cycle->degreeLevels()->sync([$master->id]);
        foreach (range(1, $n) as $i) {
            CycleOffering::create(['cycle_id' => $cycle->id, 'degree_level_id' => $master->id, 'name' => 'Major Number '.$i, 'institution' => $i % 2 ? 'North University' : 'South University', 'sort' => $i]);
        }
        CycleOffering::create(['cycle_id' => $cycle->id, 'degree_level_id' => $master->id, 'name' => 'Marine Biotechnology', 'institution' => 'Ocean University', 'sort' => $n + 1]);

        return $cycle;
    }

    public function test_the_page_shows_thirty_and_links_to_the_full_searchable_list(): void
    {
        $cycle = $this->cycleWithMajors(60);
        $show = route('scholarships.show', [$cycle->programme->slug, $cycle->slug]);

        $this->get($show)->assertOk()->assertSee('61 programmes listed')->assertSee('Major Number 30')->assertDontSee('Major Number 45')
            ->assertSee('See all 61', false)->assertSee(route('scholarships.majors', [$cycle->programme->slug, $cycle->slug, 'level' => 'master']), false);

        $list = route('scholarships.majors', [$cycle->programme->slug, $cycle->slug]);
        $this->get($list)->assertOk()->assertSee('61 programmes and majors are listed')->assertSee('Major Number 45');
        $this->get($list.'?q=marine')->assertOk()->assertSee('Marine Biotechnology')->assertSee('Ocean University')->assertDontSee('Major Number 1<', false);
        $this->get($list.'?q=zzzz')->assertOk()->assertSee('No listed programme matches');
        $this->get($list.'?page=9')->assertNotFound();
    }

    public function test_the_full_list_is_in_the_sitemap_only_when_the_page_does_not_show_everything(): void
    {
        $many = $this->cycleWithMajors(40);
        $this->assertStringContainsString(route('scholarships.majors', [$many->programme->slug, $many->slug]), $this->sitemapXml('scholarships'));

        $few = ScholarshipCycle::factory()->published()->create();
        $few->offerings()->create(['degree_level_id' => DegreeLevel::where('code', 'master')->value('id'), 'name' => 'Only Major']);
        Cache::flush();
        $this->assertStringNotContainsString(route('scholarships.majors', [$few->programme->slug, $few->slug]), $this->sitemapXml('scholarships'));
    }

    public function test_a_scholarship_without_listed_majors_has_no_list_page(): void
    {
        $this->seed(TaxonomySeeder::class);
        $cycle = ScholarshipCycle::factory()->published()->create();
        $this->get(route('scholarships.majors', [$cycle->programme->slug, $cycle->slug]))->assertNotFound();
    }
}
