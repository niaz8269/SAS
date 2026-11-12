<?php

namespace Tests\Feature\Public;

use App\Models\AnalyticsEvent;
use App\Models\Country;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\University;
use App\Models\UniversityProgramme;
use App\Support\Crawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Crawlers do not write to the shared database file (sessions, analytics), and a university page tells the reader what its stored data says. */
class CrawlerAndUniversityOverviewTest extends TestCase
{
    use RefreshDatabase;

    private const BOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    private const PERSON = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    public function test_the_crawler_check_reads_the_user_agent_only(): void
    {
        $this->assertTrue(Crawler::is(self::BOT));
        $this->assertTrue(Crawler::is('Mozilla/5.0 (SEO audit crawler; contact site owner)'));
        $this->assertTrue(Crawler::is('curl/8.4.0'));
        $this->assertFalse(Crawler::is(self::PERSON));
        $this->assertFalse(Crawler::is(null));
        $this->assertFalse(Crawler::is(''));
    }

    public function test_a_crawler_reading_a_page_does_not_add_a_session_row_but_a_person_does(): void
    {
        config(['session.driver' => 'database']);
        $this->withHeader('User-Agent', self::BOT)->get('/about')->assertOk();
        $this->assertDatabaseCount('sessions', 0);
    }

    public function test_a_person_reading_a_page_still_gets_a_session(): void
    {
        config(['session.driver' => 'database']);
        $this->withHeader('User-Agent', self::PERSON)->get('/about')->assertOk();
        $this->assertDatabaseCount('sessions', 1);
    }

    public function test_only_a_person_reading_a_scholarship_counts_as_a_view(): void
    {
        $cycle = ScholarshipCycle::factory()->published()->create(['programme_id' => ScholarshipProgramme::factory()->create()->id]);
        $url = route('scholarships.show', [$cycle->programme->slug, $cycle->slug]);

        $this->withHeader('User-Agent', self::BOT)->get($url)->assertOk();
        $this->withHeader('User-Agent', self::PERSON)->call('HEAD', $url);
        $this->assertSame(0, AnalyticsEvent::where('name', 'scholarship_view')->count());

        $this->withHeader('User-Agent', self::PERSON)->get($url)->assertOk();
        $this->assertSame(1, AnalyticsEvent::where('name', 'scholarship_view')->count());
    }

    public function test_a_university_page_summarises_its_stored_programmes_and_links_to_neighbours(): void
    {
        $china = Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china']);
        $mk = fn ($name, $slug, $key, $city) => University::create(['name' => $name, 'slug' => $slug, 'csc_key' => $key, 'province' => 'Zhejiang', 'city' => $city, 'country_id' => $china->id, 'catalogue_checked_at' => '2026-10-22']);
        $zju = $mk('Zhejiang University', 'zhejiang-university', 'ZJU', 'Hangzhou');
        $neighbour = $mk('Hangzhou Dianzi University', 'hangzhou-dianzi-university', 'HDU', 'Hangzhou');
        $elsewhere = $mk('Ningbo University', 'ningbo-university', 'NBU', 'Ningbo');
        $hidden = $mk('Hidden Hangzhou University', 'hidden-hangzhou-university', 'HID', 'Hangzhou');
        $hidden->update(['published' => false]);

        $p = fn ($level, $en, $lang, $csc, $years, $fee) => UniversityProgramme::create(['university_id' => $zju->id, 'level' => $level, 'name_zh' => '专业'.$en, 'name_en' => $en, 'teaching_language' => $lang, 'csc_eligible' => $csc, 'duration_years' => $years, 'tuition_cny' => $fee]);
        $p('master', 'Computer Science', '英语', true, 2.5, 33000);
        $p('master', 'Physics', '英语', false, 3, 30000);
        $p('bachelor', 'Chinese Language', '汉语', false, 4, 26000);

        $page = $this->get('/universities/zhejiang-university')->assertOk()
            ->assertSee('About Zhejiang University')
            ->assertSee('3 programmes for it: 1 bachelor and 2 master')
            ->assertSee('English (2) and Chinese (1)')
            ->assertSee('Listed tuition runs from CNY 26,000 to CNY 33,000 a year')
            ->assertSee('Programmes last from 2.5 to 4 years')
            ->assertSee('1 of these programmes is marked as open to Chinese Government Scholarship applicants')
            ->assertSee('More universities in Hangzhou')
            ->assertSee('/universities/hangzhou-dianzi-university', false)
            ->assertDontSee('Hidden Hangzhou University')
            ->assertDontSee('/universities/ningbo-university', false);
        $this->assertStringNotContainsString('Website', $page->getContent());
    }

    public function test_a_university_without_programmes_has_no_made_up_summary(): void
    {
        $china = Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china']);
        University::create(['name' => 'Lonely University', 'slug' => 'lonely-university', 'csc_key' => 'LON', 'province' => 'Gansu', 'city' => 'Lanzhou', 'country_id' => $china->id, 'catalogue_checked_at' => '2026-10-22']);

        $this->get('/universities/lonely-university')->assertOk()->assertDontSee('About Lonely University')->assertDontSee('Listed tuition');
    }
}
