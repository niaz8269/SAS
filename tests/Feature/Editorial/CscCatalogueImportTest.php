<?php

namespace Tests\Feature\Editorial;

use App\Models\Country;
use App\Models\University;
use App\Models\UniversityProgramme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CscCatalogueImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_universities_and_programmes_idempotently(): void
    {
        Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china']);
        $file = tempnam(sys_get_temp_dir(), 'csc').'.json';
        file_put_contents($file, json_encode([[
            'loc' => '北京--北京', 'name' => '测试大学', 'key' => 'abc123',
            'levels' => [
                '本科' => [['暂无信息', 0]],
                '硕研' => [['社会医学', 1, '3', '英语', '25000'], ['材料学', 0, '2.5', '汉语', '30000']],
            ],
        ]]));

        $this->artisan('sas:import-csc-catalogue', ['file' => $file])->assertSuccessful();
        $this->artisan('sas:import-csc-catalogue', ['file' => $file])->assertSuccessful();

        $uni = University::where('name_zh', '测试大学')->firstOrFail();
        $this->assertSame('Beijing', $uni->province);
        $this->assertSame(1, University::where('name_zh', '测试大学')->count());
        $this->assertSame(2, UniversityProgramme::count());
        $p = UniversityProgramme::where('name_zh', '社会医学')->firstOrFail();
        $this->assertTrue($p->csc_eligible);
        $this->assertSame(25000, $p->tuition_cny);
        $this->assertSame('English', $p->languageLabel());
        unlink($file);
    }
}
