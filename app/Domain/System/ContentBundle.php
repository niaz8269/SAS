<?php

namespace App\Domain\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * The site's content (scholarships, universities, fees, guides, taxonomy, homepage and menus) as one portable file, so what was built and
 * checked on one machine can be loaded into an empty live database. No user, student, case, payment or audit data is ever included,
 * and the columns that point at staff accounts are blanked. The file is gzip-compressed JSON lines: a header, then for every table
 * a line naming its columns followed by one line per row.
 */
class ContentBundle
{
    public const FORMAT = 'sas-content-1';

    /** Parents before children, so rows can be inserted in this order. */
    public const TABLES = [
        'countries', 'degree_levels', 'subjects', 'subject_aliases', 'funders', 'document_kinds',
        'universities', 'university_programmes', 'university_fee_structures', 'university_scholarships',
        'scholarship_programmes', 'document_rules', 'programme_university', 'scholarship_cycles', 'cycle_slug_redirects', 'scholarship_routes', 'source_evidence',
        'cycle_benefits', 'cycle_requirements', 'cycle_criteria', 'cycle_deadlines', 'cycle_milestones', 'cycle_offerings',
        'cycle_degree_levels', 'cycle_destinations', 'cycle_nationalities', 'cycle_excluded_nationalities', 'cycle_subjects', 'cycle_universities',
        'cycle_search_index', 'services', 'blog_categories', 'blog_posts', 'menu_items', 'home_sections', 'page_blocks', 'trust_items',
    ];

    /** If any of these has a row the site is in use, and content must not be replaced underneath it. */
    private const PEOPLE_TABLES = ['service_cases', 'documents', 'student_profiles'];

    /** Columns that hold a staff account id: blanked on export, because those accounts do not exist on the other side. */
    private const USER_COLUMNS = [
        'scholarship_cycles' => ['verified_by', 'created_by', 'approved_by'], 'scholarship_programmes' => ['created_by'],
        'cycle_deadlines' => ['changed_by'], 'source_evidence' => ['created_by'], 'services' => ['created_by', 'updated_by'],
        'page_blocks' => ['updated_by'], 'blog_posts' => ['author_id', 'reviewed_by'], 'home_sections' => ['updated_by'], 'menu_items' => ['updated_by'],
    ];

    /** Tables whose rows belong to a cycle, filtered to the exported cycles. */
    private const BY_CYCLE = [
        'cycle_slug_redirects', 'scholarship_routes', 'source_evidence', 'cycle_benefits', 'cycle_requirements', 'cycle_criteria', 'cycle_deadlines', 'cycle_milestones', 'cycle_offerings',
        'cycle_degree_levels', 'cycle_destinations', 'cycle_nationalities', 'cycle_excluded_nationalities', 'cycle_subjects', 'cycle_universities', 'cycle_search_index',
    ];

    /** @return array<string,int> rows written per table */
    public function export(string $path): array
    {
        $gz = gzopen($path, 'wb9');
        if (! $gz) {
            throw new RuntimeException('Cannot write '.$path);
        }
        $last = DB::table('migrations')->orderByDesc('id')->value('migration');
        gzwrite($gz, json_encode(['format' => self::FORMAT, 'exported_at' => now()->toIso8601String(), 'last_migration' => $last, 'tables' => self::TABLES])."\n");

        $cycleIds = DB::table('scholarship_cycles')->where('external_key', 'not like', 'partner-%')->pluck('id')->all();
        $programmeIds = DB::table('scholarship_cycles')->whereIn('id', $cycleIds)->pluck('programme_id')->unique()->all();
        $funderIds = DB::table('scholarship_programmes')->whereIn('id', $programmeIds)->pluck('funder_id')->filter()->unique()->all();
        $counts = [];

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $columns = Schema::getColumnListing($table);
            $blank = self::USER_COLUMNS[$table] ?? [];
            $query = DB::table($table);
            match (true) {
                $table === 'scholarship_cycles' => $query->whereIn('id', $cycleIds),
                $table === 'scholarship_programmes' => $query->whereIn('id', $programmeIds),
                $table === 'funders' => $query->whereIn('id', $funderIds),
                $table === 'programme_university' => $query->whereIn('programme_id', $programmeIds),
                in_array($table, self::BY_CYCLE, true) => $query->whereIn('cycle_id', $cycleIds),
                default => null,
            };
            $pk = in_array('id', $columns, true) ? 'id' : $columns[0];
            gzwrite($gz, json_encode(['table' => $table, 'columns' => $columns])."\n");
            $n = 0;
            $query->orderBy($pk)->chunk(1000, function ($rows) use ($gz, $columns, $blank, &$n) {
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($columns as $c) {
                        $values[] = in_array($c, $blank, true) ? null : $row->{$c};
                    }
                    gzwrite($gz, json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                    $n++;
                }
            });
            $counts[$table] = $n;
        }
        gzclose($gz);

        return $counts;
    }

    /**
     * Loads a bundle into a database whose content tables are empty (or, with $replace, empties them first).
     *
     * @return array<string,int> rows loaded per table
     */
    public function import(string $path, bool $replace = false): array
    {
        $gz = gzopen($path, 'rb');
        if (! $gz) {
            throw new RuntimeException('Cannot read '.$path);
        }
        $header = json_decode((string) gzgets($gz), true);
        if (($header['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('This is not a SAS content bundle.');
        }
        $busy = collect(self::TABLES)->filter(fn ($t) => Schema::hasTable($t) && DB::table($t)->exists());
        if ($busy->isNotEmpty() && ! $replace) {
            throw new RuntimeException('The content tables are not empty ('.$busy->take(3)->implode(', ').'...). Use --replace to empty them first.');
        }
        // Replacing would break (or silently orphan) anything that points at the old rows, so it is only for a site nobody has used yet.
        foreach (self::PEOPLE_TABLES as $people) {
            if ($replace && Schema::hasTable($people) && DB::table($people)->exists()) {
                throw new RuntimeException('Refusing to replace content: '.$people.' already has rows. Replace is only for a database with no student data.');
            }
        }

        $counts = [];
        Schema::disableForeignKeyConstraints(); // the bundle is complete and ordered, but seeded rows and cross-links make a strict check a nuisance
        try {
            DB::transaction(function () use ($gz, $replace, &$counts) {
                $this->load($gz, $replace, $counts);
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        gzclose($gz);

        return $counts;
    }

    private function load($gz, bool $replace, array &$counts): void
    {
        if ($replace) {
            foreach (array_reverse(self::TABLES) as $table) {
                Schema::hasTable($table) && DB::table($table)->delete();
            }
        }
        $table = null;
        $columns = [];
        $keep = [];
        $batch = [];
        $flush = function () use (&$batch, &$table) {
            if ($batch !== [] && $table) {
                foreach (array_chunk($batch, 200) as $part) {
                    DB::table($table)->insert($part);
                }
            }
            $batch = [];
        };
        while (($line = gzgets($gz)) !== false) {
            $data = json_decode($line, true);
            if (isset($data['table'])) {
                $flush();
                $table = Schema::hasTable($data['table']) && in_array($data['table'], self::TABLES, true) ? $data['table'] : null;
                $columns = $data['columns'];
                $keep = $table ? array_flip(Schema::getColumnListing($table)) : [];
                $counts[$data['table']] = 0;

                continue;
            }
            if (! $table || ! is_array($data)) {
                continue;
            }
            $row = [];
            foreach ($columns as $i => $c) {
                isset($keep[$c]) && $row[$c] = $data[$i] ?? null; // a column the database no longer has is dropped
            }
            $batch[] = $row;
            $counts[$table]++;
            count($batch) >= 1000 && $flush();
        }
        $flush();
    }
}
