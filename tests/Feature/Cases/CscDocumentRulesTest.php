<?php

namespace Tests\Feature\Cases;

use App\Domain\Cases\CaseService;
use App\Domain\Documents\Requirements;
use App\Models\DegreeLevel;
use App\Models\DocumentRule;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\User;
use Database\Seeders\CscDocumentRulesSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CscDocumentRulesTest extends TestCase
{
    use RefreshDatabase;

    private function csc(): ScholarshipCycle
    {
        $this->seed(TaxonomySeeder::class);
        $programme = ScholarshipProgramme::factory()->create(['slug' => CscDocumentRulesSeeder::SLUG, 'title' => 'Chinese Government Scholarship (CSC)']);

        return ScholarshipCycle::factory()->published()->create(['programme_id' => $programme->id]);
    }

    private function rows(ScholarshipCycle $cycle, string $levelCode): array
    {
        $case = app(CaseService::class)->open(User::factory()->create(), $cycle, null, []);
        app(CaseService::class)->setDegreeLevel($case, DegreeLevel::where('code', $levelCode)->value('id'));

        return app(Requirements::class)->forCase($case->refresh()->load('cycle'))->mapWithKeys(fn ($r) => [$r['kind']->key => $r])->all();
    }

    public function test_the_seeder_sets_the_csc_rules_per_degree_level(): void
    {
        $cycle = $this->csc();
        $this->seed(CscDocumentRulesSeeder::class);

        $bachelor = $this->rows($cycle, 'bachelor');
        $this->assertSame('required', $bachelor['csca']['requirement']);
        $this->assertSame('optional', $bachelor['recommendation']['requirement']);

        foreach (['master', 'doctorate'] as $code) {
            $rows = $this->rows($cycle, $code);
            $this->assertArrayNotHasKey('csca', $rows, "$code: CSCA is for bachelor's applicants only");
            $this->assertSame('required', $rows['recommendation']['requirement'], $code);
        }

        foreach (['passport', 'degree_certificate', 'transcript', 'pre_admission_letter', 'study_plan', 'police_clearance', 'medical'] as $key) {
            $this->assertSame('required', $bachelor[$key]['requirement'], $key);
        }
        $this->assertSame('optional', $bachelor['chinese_language']['requirement']);
        $this->assertStringContainsString('800 words', $bachelor['study_plan']['note']);
    }

    public function test_running_the_seeder_again_changes_nothing_and_notes_fit_the_column(): void
    {
        $this->csc();
        $this->seed(CscDocumentRulesSeeder::class);
        $count = DocumentRule::count();
        $this->seed(CscDocumentRulesSeeder::class);

        $this->assertSame($count, DocumentRule::count());
        $this->assertSame(17, $count);
        DocumentRule::all()->each(fn ($r) => $this->assertLessThanOrEqual(300, mb_strlen($r->note), $r->kind->key));
    }

    public function test_other_scholarships_keep_the_defaults(): void
    {
        $csc = $this->csc();
        $this->seed(CscDocumentRulesSeeder::class);
        $other = ScholarshipCycle::factory()->published()->create();

        $rows = $this->rows($other, 'bachelor');
        $this->assertSame('optional', $rows['csca']['requirement']);
        $this->assertSame('optional', $rows['police_clearance']['requirement']);
        $this->assertNotSame($csc->programme_id, $other->programme_id);
    }

    public function test_the_seeder_does_nothing_when_the_scholarship_is_missing(): void
    {
        $this->seed(CscDocumentRulesSeeder::class);

        $this->assertSame(0, DocumentRule::count());
    }
}
