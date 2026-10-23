<?php

namespace Tests\Feature\Cases;

use App\Models\DocumentKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The committed data/document-rules files must stay valid, sourced and consistent, whatever is in the database. */
class DocumentRuleFilesTest extends TestCase
{
    use RefreshDatabase;

    private const LEVELS = ['bachelor', 'master', 'doctorate', 'postdoctoral', 'diploma'];

    public function test_every_rule_file_is_well_formed_and_sourced(): void
    {
        $files = glob(base_path('data/document-rules/*.json'));
        $this->assertNotEmpty($files);
        $known = DocumentKind::pluck('key')->all();

        foreach ($files as $file) {
            $name = basename($file);
            $d = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($file)), true);
            $this->assertIsArray($d, "$name is not valid JSON");
            $baseline = ($d['baseline'] ?? false) === true; // SAS's own rules for a degree level: no slug, no funder source
            $baseline || $this->assertSame(basename($file, '.json'), $d['slug'] ?? null, "$name: the file name must be the scholarship slug");
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $d['checked_on'] ?? '', "$name: checked_on");
            $baseline || $this->assertNotEmpty($d['sources'] ?? [], "$name: no sources");
            foreach ($d['sources'] as $url) {
                $this->assertStringStartsWith('https://', $url, "$name: source");
            }
            $declared = array_column($d['new_kinds'] ?? [], 'key');
            $seen = [];
            foreach ($d['rules'] as $r) {
                $id = "$name / ".($r['kind'] ?? '?');
                $this->assertContains($r['kind'], array_merge($known, $declared), "$id: unknown document type");
                $this->assertContains($r['requirement'], ['required', 'optional', 'not_needed'], "$id: requirement");
                $this->assertTrue(($r['level'] ?? null) === null || in_array($r['level'], self::LEVELS, true), "$id: level");
                $this->assertLessThanOrEqual(300, mb_strlen((string) ($r['note'] ?? '')), "$id: note length");
                if (isset($r['source'])) {
                    $this->assertStringStartsWith('https://', $r['source'], "$id: source");
                }
                $key = $r['kind'].'@'.($r['level'] ?? '*');
                $this->assertNotContains($key, $seen, "$name: duplicate rule $key");
                $seen[] = $key;
            }
            foreach ($baseline ? [] : ['passport', 'photo', 'degree_certificate', 'transcript'] as $basic) {
                $this->assertContains($basic, array_column($d['rules'], 'kind'), "$name: no explicit $basic rule (the default would be mandatory)");
            }
            $baseline && $this->assertNotContains(null, array_column($d['rules'], 'level'), "$name: a baseline rule needs a degree level");
        }
    }
}
