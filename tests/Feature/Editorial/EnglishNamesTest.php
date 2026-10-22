<?php

namespace Tests\Feature\Editorial;

use App\Domain\Catalogue\ChinaPlaces;
use App\Models\Country;
use App\Models\University;
use App\Models\UniversityProgramme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnglishNamesTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalogue(): array
    {
        $china = Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china']);
        $u = University::create(['name' => '北京大学', 'slug' => 'csc-a', 'name_zh' => '北京大学', 'country_id' => $china->id]);
        $p = UniversityProgramme::create(['university_id' => $u->id, 'level' => 'master', 'name_zh' => '法学', 'teaching_language' => '英语']);

        return [$u, $p];
    }

    private function file(array $map): string
    {
        $f = tempnam(sys_get_temp_dir(), 'en').'.json';
        file_put_contents($f, json_encode($map, JSON_UNESCAPED_UNICODE));

        return $f;
    }

    public function test_english_names_are_applied_and_marked_to_verify(): void
    {
        [$u, $p] = $this->seedCatalogue();
        $this->artisan('sas:import-english-names', ['file' => $this->file(['北京大学' => 'Peking University']), '--type' => 'universities'])->assertSuccessful();
        $this->artisan('sas:import-english-names', ['file' => $this->file(['法学' => 'Law']), '--type' => 'programmes'])->assertSuccessful();

        $this->assertSame('Peking University', $u->fresh()->name);
        $this->assertSame('translated', $u->fresh()->name_en_source);
        $this->assertSame('Law', $p->fresh()->name_en);
        $this->assertSame('Law', $p->fresh()->displayName());
        $this->assertSame('translated', $p->fresh()->name_en_source);
    }

    public function test_staff_edits_are_never_overwritten_and_official_beats_translated(): void
    {
        [$u, $p] = $this->seedCatalogue();
        $p->update(['name_en' => 'Jurisprudence']);
        $this->assertSame('staff', $p->fresh()->name_en_source);
        $u->update(['name' => 'Peking Univ.']);
        $this->assertSame('staff', $u->fresh()->name_en_source);

        $this->artisan('sas:import-english-names', ['file' => $this->file(['法学' => 'Law']), '--type' => 'programmes', '--source' => 'official']);
        $this->artisan('sas:import-english-names', ['file' => $this->file(['北京大学' => 'Peking University']), '--type' => 'universities', '--source' => 'official']);
        $this->assertSame('Jurisprudence', $p->fresh()->name_en);
        $this->assertSame('Peking Univ.', $u->fresh()->name);

        [$u2, $p2] = [University::create(['name' => 'X', 'slug' => 'x', 'name_zh' => '清华大学', 'name_en_source' => 'official']), null];
        $this->artisan('sas:import-english-names', ['file' => $this->file(['清华大学' => 'Wrong Machine Name']), '--type' => 'universities', '--source' => 'translated']);
        $this->assertSame('X', $u2->fresh()->name);
    }

    public function test_chinese_or_empty_values_are_rejected_and_display_falls_back_to_chinese(): void
    {
        [$u, $p] = $this->seedCatalogue();
        $this->artisan('sas:import-english-names', ['file' => $this->file(['法学' => '法学', '北京大学' => '']), '--type' => 'programmes']);
        $this->assertNull($p->fresh()->name_en);
        $this->assertSame('法学', $p->fresh()->displayName());
    }

    public function test_places_and_languages_are_shown_in_english(): void
    {
        $this->assertSame('Guangzhou, Zhuhai, Shenzhen', ChinaPlaces::cities('广州、珠海、深圳')['city']);
        $this->assertSame('Fuxin, Huludao', ChinaPlaces::cities('阜新；辽宁, 葫芦岛')['city']);
        $this->assertSame('Nanning', ChinaPlaces::cities('南宁市')['city']);
        $this->assertSame('Tibet (Xizang)', ChinaPlaces::province('西藏'));
        foreach (['汉语' => 'Chinese', '中文' => 'Chinese', '英语' => 'English', '英文' => 'English', '汉语,英语' => 'Chinese and English', '汉语Chinese/英语English' => 'Chinese and English', '双语' => 'Bilingual (Chinese and English)', null => 'Not listed'] as $raw => $en) {
            $this->assertSame($en, UniversityProgramme::languageLabelFor($raw === '' ? null : $raw));
        }
    }
}
