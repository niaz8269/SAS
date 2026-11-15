<?php

namespace Tests\Feature\Editorial;

use App\Models\University;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyUniversitySummariesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_blank_summaries_only_and_is_repeatable(): void
    {
        $blank = University::create(['name' => 'Blank University', 'slug' => 'blank-uni']);
        $kept = University::create(['name' => 'Kept University', 'slug' => 'kept-uni', 'summary' => 'Written in the Admin.']);

        $path = tempnam(sys_get_temp_dir(), 'uni');
        file_put_contents($path, json_encode([
            ['slug' => 'blank-uni', 'summary' => 'A <b>public</b> university.', 'website' => 'https://example.edu.cn/'],
            ['slug' => 'kept-uni', 'summary' => 'Replacement text.'],
            ['slug' => 'nobody', 'summary' => 'Unknown.'],
        ]));

        $this->artisan('sas:apply-university-summaries', ['files' => [$path], '--dry-run' => true])->assertSuccessful();
        $this->assertNull($blank->fresh()->summary);

        $this->artisan('sas:apply-university-summaries', ['files' => [$path]])->assertSuccessful();
        $this->assertSame('A public university.', $blank->fresh()->summary);
        $this->assertSame('https://example.edu.cn/', $blank->fresh()->website);
        $this->assertSame('Written in the Admin.', $kept->fresh()->summary);
    }
}
