<?php

namespace App\Domain\Documents;

use App\Models\DegreeLevel;
use App\Models\DocumentKind;
use App\Models\DocumentRule;
use App\Models\ScholarshipProgramme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loads document rules for a scholarship from a JSON file (see data/document-rules/README.md).
 * One file per scholarship, so a rule set can be reviewed, versioned and applied to any scholarship added later.
 * It is additive and idempotent: the same file gives the same rules, and rules not in the file are left alone.
 */
class RulesImporter
{
    /** @return array{errors:string[],created:int,updated:int,rules:int,programme:?string} */
    public function import(array $file, bool $dryRun = false): array
    {
        $report = ['errors' => [], 'created' => 0, 'updated' => 0, 'rules' => 0, 'programme' => $file['slug'] ?? null];
        $programme = ScholarshipProgramme::where('slug', $file['slug'] ?? '')->first();
        if (! $programme) {
            $report['errors'][] = 'No scholarship with slug "'.($file['slug'] ?? '').'".';

            return $report;
        }

        $newKinds = collect($file['new_kinds'] ?? [])->keyBy('key');
        $kinds = DocumentKind::pluck('id', 'key');
        $levels = DegreeLevel::pluck('id', 'code');
        $sources = array_values(array_filter((array) ($file['sources'] ?? []), fn ($u) => is_string($u) && str_starts_with($u, 'https://')));
        $checked = $file['checked_on'] ?? null;
        if ($checked !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $checked)) {
            $report['errors'][] = 'checked_on must be YYYY-MM-DD.';
        }
        if (empty($file['rules'])) {
            $report['errors'][] = 'The file has no rules.';
        }
        if (! $sources) {
            $report['errors'][] = 'At least one https source URL is required.';
        }

        $plan = [];
        foreach ($file['rules'] ?? [] as $i => $r) {
            $label = 'rule '.($i + 1).' ('.($r['kind'] ?? '?').')';
            $key = $r['kind'] ?? '';
            if (! isset($kinds[$key]) && ! $newKinds->has($key)) {
                $report['errors'][] = "$label: unknown document type.";
            }
            if (! in_array($r['requirement'] ?? '', array_keys(DocumentKind::REQUIREMENTS), true)) {
                $report['errors'][] = "$label: requirement must be required, optional or not_needed.";
            }
            $level = $r['level'] ?? null;
            if ($level !== null && ! isset($levels[$level])) {
                $report['errors'][] = "$label: unknown degree level \"$level\".";
            }
            $note = trim((string) ($r['note'] ?? ''));
            if (mb_strlen($note) > 300) {
                $report['errors'][] = "$label: the note is longer than 300 characters.";
            }
            $source = $r['source'] ?? ($sources[0] ?? null);
            if ($source !== null && ! str_starts_with($source, 'https://')) {
                $report['errors'][] = "$label: the source must be an https URL.";
            }
            $plan[] = [$key, $level, $r['requirement'] ?? '', $note ?: null, $source];
        }
        foreach ($newKinds as $k) {
            if (! preg_match('/^[a-z][a-z0-9_]{2,39}$/', $k['key'] ?? '') || blank($k['name'] ?? null)) {
                $report['errors'][] = 'new_kinds needs a snake_case key and a name.';
            }
        }
        if ($report['errors']) {
            return $report;
        }

        $report['rules'] = count($plan);
        if ($dryRun) {
            return $report;
        }

        DB::transaction(function () use ($plan, $newKinds, $programme, $levels, $checked, &$report) {
            foreach ($newKinds as $k) {
                DocumentKind::firstOrCreate(['key' => $k['key']], ['name' => Str::limit($k['name'], 120, ''), 'description' => isset($k['description']) ? Str::limit($k['description'], 300, '') : null, 'default_requirement' => 'optional', 'sort' => 140, 'active' => true]);
            }
            $kinds = DocumentKind::pluck('id', 'key');
            foreach ($plan as [$key, $level, $requirement, $note, $source]) {
                $rule = DocumentRule::firstOrNew(['kind_id' => $kinds[$key], 'programme_id' => $programme->id, 'degree_level_id' => $level ? $levels[$level] : null]);
                $rule->exists ? $report['updated']++ : $report['created']++;
                $rule->fill(['requirement' => $requirement, 'note' => $note, 'source_url' => $source, 'checked_on' => $checked])->save();
            }
        });

        return $report;
    }
}
