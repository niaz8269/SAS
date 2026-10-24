<?php

namespace Tests\Feature\Cases;

use App\Domain\Cases\CaseService;
use App\Domain\Documents\Requirements;
use App\Domain\Documents\RulesCopier;
use App\Enums\Role;
use App\Filament\Resources\ScholarshipCycles\Pages\EditScholarshipCycle;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\DocumentRulesRelationManager;
use App\Models\DocumentKind;
use App\Models\DocumentRule;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentRulesImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        $this->dir = sys_get_temp_dir().'/sas-rules-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    private function file(array $data, string $name = 'a.json'): string
    {
        file_put_contents($path = $this->dir.'/'.$name, json_encode($data));

        return $path;
    }

    private function valid(string $slug): array
    {
        return ['slug' => $slug, 'checked_on' => '2026-10-24', 'sources' => ['https://example.org/apply'], 'rules' => [
            ['kind' => 'passport', 'level' => null, 'requirement' => 'required', 'note' => 'Valid passport.'],
            ['kind' => 'recommendation', 'level' => 'master', 'requirement' => 'required', 'note' => 'Two letters.', 'source' => 'https://example.org/refs'],
            ['kind' => 'photo', 'requirement' => 'optional'],
        ]];
    }

    public function test_a_file_creates_rules_with_their_sources_and_is_safe_to_repeat(): void
    {
        $programme = ScholarshipProgramme::factory()->create(['slug' => 'demo-award']);
        $path = $this->file($this->valid('demo-award'));

        $this->artisan('sas:import-document-rules', ['path' => $path])->assertSuccessful();
        $this->assertSame(3, DocumentRule::where('programme_id', $programme->id)->count());
        $rule = DocumentRule::whereHas('kind', fn ($q) => $q->where('key', 'recommendation'))->firstOrFail();
        $this->assertSame('https://example.org/refs', $rule->source_url);
        $this->assertSame('2026-10-24', $rule->checked_on->toDateString());
        $this->assertNotNull($rule->degree_level_id);

        $this->artisan('sas:import-document-rules', ['path' => $path])->assertSuccessful();
        $this->assertSame(3, DocumentRule::count(), 'importing again changes nothing');
    }

    public function test_an_imported_scholarship_gets_its_rules_on_a_case(): void
    {
        $cycle = ScholarshipCycle::factory()->published()->create();
        $cycle->programme->update(['slug' => 'demo-award']);
        $this->artisan('sas:import-document-rules', ['path' => $this->file($this->valid('demo-award'))])->assertSuccessful();

        $case = app(CaseService::class)->open(User::factory()->create(), $cycle, null, []);
        $rows = app(Requirements::class)->forCase($case)->keyBy(fn ($r) => $r['kind']->key);
        $this->assertSame('optional', $rows['photo']['requirement'], 'the file overrides the default');
        $this->assertSame('https://example.org/apply', $rows['passport']['source']);
    }

    public function test_bad_files_are_rejected_and_change_nothing(): void
    {
        ScholarshipProgramme::factory()->create(['slug' => 'demo-award']);
        $bad = $this->valid('demo-award');
        $bad['rules'][] = ['kind' => 'made_up', 'requirement' => 'required'];
        $bad['rules'][] = ['kind' => 'cv', 'level' => 'nonsense', 'requirement' => 'maybe', 'note' => str_repeat('x', 301)];
        $bad['sources'] = ['http://insecure.example'];

        $this->artisan('sas:import-document-rules', ['path' => $this->file($bad)])->assertFailed();
        $this->artisan('sas:import-document-rules', ['path' => $this->file($this->valid('missing-slug'), 'b.json')])->assertFailed();
        $this->assertSame(0, DocumentRule::count());
    }

    public function test_a_dry_run_changes_nothing_and_new_kinds_can_be_declared(): void
    {
        ScholarshipProgramme::factory()->create(['slug' => 'demo-award']);
        $data = $this->valid('demo-award');
        $data['new_kinds'] = [['key' => 'lab_safety_form', 'name' => 'Lab safety form']];
        $data['rules'][] = ['kind' => 'lab_safety_form', 'requirement' => 'required', 'note' => 'Signed by your supervisor.'];
        $path = $this->file($data);

        $this->artisan('sas:import-document-rules', ['path' => $path, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, DocumentRule::count());
        $this->assertNull(DocumentKind::firstWhere('key', 'lab_safety_form'));

        $this->artisan('sas:import-document-rules', ['path' => $path])->assertSuccessful();
        $this->assertNotNull(DocumentKind::firstWhere('key', 'lab_safety_form'));
        $this->assertSame(4, DocumentRule::count());
    }

    public function test_a_byte_order_mark_in_the_file_is_tolerated(): void
    {
        ScholarshipProgramme::factory()->create(['slug' => 'demo-award']);
        file_put_contents($path = $this->dir.'/bom.json', "\xEF\xBB\xBF".json_encode($this->valid('demo-award')));

        $this->artisan('sas:import-document-rules', ['path' => $path])->assertSuccessful();
        $this->assertSame(3, DocumentRule::count());
    }

    public function test_copying_rules_to_a_new_scholarship_keeps_sources_but_not_the_checked_date(): void
    {
        $from = ScholarshipProgramme::factory()->create(['slug' => 'old-award']);
        $to = ScholarshipProgramme::factory()->create(['slug' => 'new-award']);
        $this->artisan('sas:import-document-rules', ['path' => $this->file($this->valid('old-award'))])->assertSuccessful();

        $this->assertSame(3, app(RulesCopier::class)->copy($from, $to));
        $copied = DocumentRule::where('programme_id', $to->id)->get();
        $this->assertCount(3, $copied);
        $this->assertTrue($copied->every(fn ($r) => $r->checked_on === null && $r->source_url !== null));

        $this->assertSame(0, app(RulesCopier::class)->copy($from, $to), 'copying again adds nothing');
        DocumentRule::where('programme_id', $to->id)->first()->update(['requirement' => 'not_needed']);
        $this->assertSame(3, app(RulesCopier::class)->copy($from, $to, replace: true));
        $this->assertSame(0, DocumentRule::where('programme_id', $to->id)->where('requirement', 'not_needed')->count());
    }

    public function test_the_copy_action_is_in_the_admin_and_only_for_document_managers(): void
    {
        Filament::setCurrentPanel('admin');
        $manager = User::factory()->role(Role::Administrator)->create();
        $manager->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['a-b']])->save();
        $from = ScholarshipCycle::factory()->published()->create();
        $to = ScholarshipCycle::factory()->published()->create();
        DocumentRule::create(['kind_id' => DocumentKind::where('key', 'csca')->value('id'), 'programme_id' => $from->programme_id, 'requirement' => 'required', 'note' => 'x']);

        $this->actingAs($manager);
        Livewire::test(DocumentRulesRelationManager::class, ['ownerRecord' => $to, 'pageClass' => EditScholarshipCycle::class])
            ->callTableAction('copyRules', data: ['from' => $from->programme_id, 'replace' => false]);
        $this->assertSame(1, DocumentRule::where('programme_id', $to->programme_id)->count());

        auth()->logout();
        $editor = User::factory()->role(Role::Advisor)->create();
        $editor->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['a-b']])->save();
        $this->actingAs($editor);
        $this->assertFalse(DocumentRulesRelationManager::canViewForRecord($to, EditScholarshipCycle::class));
    }
}
