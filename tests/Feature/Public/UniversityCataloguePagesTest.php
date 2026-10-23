<?php

namespace Tests\Feature\Public;

use App\Models\Country;
use App\Models\PageBlock;
use App\Models\University;
use App\Models\UniversityProgramme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniversityCataloguePagesTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalogue(): array
    {
        $china = Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china']);
        $mk = fn ($name, $zh, $slug, $prov, $city) => University::create(['name' => $name, 'name_zh' => $zh, 'slug' => $slug, 'csc_key' => strtoupper($slug), 'province' => $prov, 'city' => $city,
            'country_id' => $china->id, 'source_url' => 'https://www.campuschina.org/zh/universities_art/index.html?key=X', 'catalogue_checked_at' => '2026-10-22']);
        $pku = $mk('Peking University', '北京大学', 'peking-university', 'Beijing', 'Beijing');
        $zju = $mk('Zhejiang University', '浙江大学', 'zhejiang-university', 'Zhejiang', 'Hangzhou');
        $hidden = $mk('Hidden University', '隐藏大学', 'hidden-university', 'Hunan', 'Changsha');
        $hidden->update(['published' => false]);

        $p = fn ($u, $level, $zh, $en, $lang, $csc, $tuition) => UniversityProgramme::create(['university_id' => $u->id, 'level' => $level, 'name_zh' => $zh, 'name_en' => $en,
            'teaching_language' => $lang, 'csc_eligible' => $csc, 'duration_years' => 4, 'tuition_cny' => $tuition]);
        $p($pku, 'master', '计算机科学与技术', 'Computer Science and Technology', '英语', true, 33000);
        $p($pku, 'bachelor', '汉语言文学', 'Chinese Language and Literature', '汉语', false, 26000);
        $p($zju, 'master', '临床医学', 'Clinical Medicine', '汉语,英语', true, 30000);
        $p($hidden, 'master', '秘密专业', 'Secret Programme', '英语', true, 1);

        return [$pku, $zju, $hidden];
    }

    public function test_university_list_shows_english_and_chinese_names_and_hides_unpublished(): void
    {
        $this->seedCatalogue();
        $this->get('/universities')->assertOk()->assertSee('Peking University')->assertSee('北京大学')->assertSee('Zhejiang University')
            ->assertDontSee('Hidden University')->assertSee('2 universities');
    }

    public function test_universities_can_be_searched_by_either_language_and_filtered_by_province(): void
    {
        $this->seedCatalogue();
        $this->get('/universities?q=peking')->assertSee('Peking University')->assertDontSee('Zhejiang University');
        $this->get('/universities?q='.urlencode('浙江'))->assertSee('Zhejiang University')->assertDontSee('Peking University');
        $this->get('/universities?province=Zhejiang')->assertSee('Zhejiang University')->assertDontSee('Peking University');
        $this->get('/universities?q=zzzz')->assertSee('No universities match your search.');
    }

    public function test_search_input_is_escaped_and_wildcards_are_literal(): void
    {
        $this->seedCatalogue();
        $html = $this->get('/universities?q='.urlencode('<script>alert(1)</script>'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->get('/universities?q='.urlencode('%'))->assertOk()->assertSee('No universities match your search.');
    }

    public function test_university_page_lists_programmes_with_filters_and_the_source_note(): void
    {
        [$pku] = $this->seedCatalogue();
        $this->get('/universities/peking-university')->assertOk()->assertSee('Peking University')->assertSee('Computer Science and Technology')
            ->assertSee('Chinese Language and Literature')->assertSee('campuschina.org')->assertSee('Programme lists, tuition')->assertDontSee('Clinical Medicine');
        $this->get('/universities/peking-university?level=bachelor')->assertSee('Chinese Language and Literature')->assertDontSee('Computer Science and Technology');
        $this->get('/universities/peking-university?csc=1')->assertSee('Computer Science and Technology')->assertDontSee('Chinese Language and Literature');
        $this->get('/universities/peking-university?language=English')->assertSee('Computer Science and Technology')->assertDontSee('Chinese Language and Literature');
        $this->get('/universities/peking-university?level=bogus')->assertSessionHasErrors('level');
    }

    public function test_hidden_and_unknown_universities_are_404(): void
    {
        $this->seedCatalogue();
        $this->get('/universities/hidden-university')->assertNotFound();
        $this->get('/universities/nope')->assertNotFound();
    }

    public function test_programme_search_filters_by_subject_level_language_province_and_scholarship_star(): void
    {
        $this->seedCatalogue();
        $this->get('/programmes')->assertOk()->assertSee('Computer Science and Technology')->assertSee('Clinical Medicine')->assertDontSee('Secret Programme');
        $this->get('/programmes?q=medicine')->assertSee('Clinical Medicine')->assertDontSee('Computer Science and Technology');
        $this->get('/programmes?q='.urlencode('临床'))->assertSee('Clinical Medicine');
        $this->get('/programmes?level=bachelor')->assertSee('Chinese Language and Literature')->assertDontSee('Clinical Medicine');
        $this->get('/programmes?language='.urlencode('Chinese and English'))->assertSee('Clinical Medicine')->assertDontSee('Computer Science and Technology');
        $this->get('/programmes?province=Zhejiang')->assertSee('Clinical Medicine')->assertDontSee('Computer Science and Technology');
        $this->get('/programmes?csc=1')->assertSee('CSC listed')->assertDontSee('Chinese Language and Literature');
    }

    public function test_a_hidden_programme_is_not_shown(): void
    {
        $this->seedCatalogue();
        UniversityProgramme::where('name_en', 'Clinical Medicine')->update(['published' => false]);
        $this->get('/programmes')->assertDontSee('Clinical Medicine');
        $this->get('/universities/zhejiang-university')->assertDontSee('Clinical Medicine');
    }

    public function test_page_text_comes_from_the_admin_and_pages_are_in_the_sitemap_and_menu(): void
    {
        $this->seedCatalogue();
        PageBlock::builtIn('universities', 'intro')->update(['heading' => 'Where to study in China']);
        \Cache::forget('page.blocks');
        $this->get('/universities')->assertSee('Where to study in China');
        $this->get('/')->assertSee('/universities', false);
        $this->get('/sitemap.xml')->assertSee('/universities/peking-university', false)->assertSee('/programmes', false)->assertDontSee('hidden-university', false);
    }

    public function test_pagination_keeps_filters(): void
    {
        [$pku] = $this->seedCatalogue();
        for ($i = 0; $i < 45; $i++) {
            UniversityProgramme::create(['university_id' => $pku->id, 'level' => 'master', 'name_zh' => "专业$i", 'name_en' => "Test Subject $i", 'teaching_language' => '英语']);
        }
        $this->get('/universities/peking-university?level=master')->assertOk()->assertSee('page=2', false)->assertSee('level=master', false);
        $this->get('/universities/peking-university?level=master&page=2')->assertOk();
    }
}
