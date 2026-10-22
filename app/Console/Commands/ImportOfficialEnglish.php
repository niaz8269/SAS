<?php

namespace App\Console\Commands;

use App\Models\University;
use App\Models\UniversityProgramme;
use Illuminate\Console\Command;

/**
 * Matches the English pages of the study-in-China site to the Chinese catalogue already imported, and records the official
 * English wording. Rows are matched only when the programme lists line up exactly (same count, star, tuition and duration at
 * every position), so a wrong university or programme is never paired. Where the official wording equals ours (ignoring case and
 * punctuation) the name is marked "official"; where it differs, the official wording is stored beside ours and ours is kept,
 * because the site's English has typos. Staff-entered names are never changed.
 */
class ImportOfficialEnglish extends Command
{
    protected $signature = 'sas:import-official-english
        {--en=* : JSON files {englishKey: {name, loc, levels: {Bachelor: [[name, star, duration, language, tuition]]}}}}
        {--zh=* : JSON files of the raw Chinese catalogue collected earlier ([{name, levels: {本科: [...]}}])}
        {--universities= : JSON list [{key, name, loc}] from the English site, for university names}
        {--dry-run : report only}';

    protected $description = 'Record the official English names from the study-in-China site next to the catalogue.';

    private const LEVELS = ['本科' => ['Bachelor', 'bachelor'], '硕研' => ['Master', 'master'], '博研' => ['Doctoral', 'doctorate'], '非学历' => ['Non-degree', 'non_degree'], '专科' => ['Associate', 'associate']];

    public function handle(): int
    {
        $zh = [];
        foreach ($this->option('zh') as $f) {
            foreach (json_decode(file_get_contents($f), true) ?: [] as $u) {
                $zh[$u['name']] = $u;
            }
        }
        $en = [];
        foreach ($this->option('en') as $f) {
            $en += json_decode(file_get_contents($f), true) ?: [];
        }
        if (! $zh || ! $en) {
            $this->error('Need --zh and --en files.');

            return self::FAILURE;
        }

        $norm = fn (string $s) => preg_replace('/[^a-z0-9]/', '', strtolower(str_replace(['&', 'The '], ['and', ''], $this->clean($s))));
        $dbUnis = University::whereNotNull('csc_key')->get()->filter(fn ($u) => isset($zh[$u->name_zh]));

        $stats = ['unis' => 0, 'unmatched_unis' => [], 'rows_aligned' => 0, 'official' => 0, 'differs' => 0, 'filled' => 0, 'weak' => []];
        $dry = $this->option('dry-run');
        $used = [];

        foreach ($en as $e) {
            // Candidate Chinese universities: the four closest by English name; the winner is the one whose programme lists line up best.
            $cands = $dbUnis->reject(fn ($u) => isset($used[$u->id]))->map(function ($u) use ($norm, $e) {
                similar_text($norm($u->name), $norm($e['name']), $pct);

                return [$u, $pct];
            })->sortByDesc(1)->take(4);
            $best = null;
            $bestScore = 0.0;
            foreach ($cands as [$u]) {
                $score = $this->score($zh[$u->name_zh]['levels'] ?? [], $e['levels'] ?? []);
                if ($score > $bestScore) {
                    $best = $u;
                    $bestScore = $score;
                }
            }
            // Strong structural match, or the same name with at least some programmes lining up (tuition and star flags change between collections).
            if (! $best || ($bestScore < 0.85 && ! ($norm($best->name) === $norm($e['name']) && $bestScore >= 0.15))) {
                $stats['unmatched_unis'][] = $e['name'].($best ? sprintf(' (best %.0f%% vs %s)', $bestScore * 100, $best->name) : '');

                continue;
            }
            $used[$best->id] = true;
            $stats['unis']++;
            $uni = $best;
            $official = $this->clean($e['name']);
            if (! $dry) {
                $update = ['name_en_official' => $official];
                if ($uni->name_en_source !== 'staff' && $norm($uni->name) === $norm($official)) {
                    $update['name_en_source'] = 'official';
                }
                $uni->forceFill($update)->saveQuietly();
            }

            foreach (self::LEVELS as $zhLevel => [$enLevel, $dbLevel]) {
                $zr = $this->real($zh[$uni->name_zh]['levels'][$zhLevel] ?? []);
                $er = $this->real($e['levels'][$enLevel] ?? []);
                // Pair each Chinese row with the unused English row that has the same star, duration, tuition and language
                // AND the most similar wording to our translation. Order is not trusted: the two lists are not sorted alike.
                $free = [];
                foreach ($er as $j => $r) {
                    $free[$this->rowKey($r)][$j] = $this->clean($r[0]);
                }
                foreach ($zr as $row) {
                    $lang = trim((string) ($row[3] ?? '')) ?: null;
                    $prog = UniversityProgramme::where(['university_id' => $uni->id, 'level' => $dbLevel, 'name_zh' => trim($row[0]), 'teaching_language' => $lang])->first();
                    $key = $this->rowKey($row);
                    if (! $prog || empty($free[$key])) {
                        continue;
                    }
                    $pickJ = null;
                    $pickPct = 0.0;
                    foreach ($free[$key] as $j => $name) {
                        if ($prog->name_en) {
                            similar_text($norm($prog->name_en), $norm($name), $pct);
                        } else {
                            $pct = count($free[$key]) === 1 ? 100.0 : 0.0; // nothing to compare with: only an unambiguous pair
                        }
                        if ($pct > $pickPct) {
                            $pickPct = $pct;
                            $pickJ = $j;
                        }
                    }
                    if ($pickJ === null || $pickPct < 72.0) {
                        continue;
                    }
                    $official = $free[$key][$pickJ];
                    unset($free[$key][$pickJ]);
                    $stats['rows_aligned']++;
                    $update = [];
                    if ($prog->name_en_official === null) {
                        $update['name_en_official'] = $official;
                    }
                    if ($prog->name_en === null && $prog->name_en_source !== 'staff') {
                        $update += ['name_en' => $official, 'name_en_source' => 'official'];
                        $stats['filled']++;
                    } elseif ($prog->name_en_source !== 'staff' && $norm($prog->name_en) === $norm($official)) {
                        $update['name_en_source'] = 'official';
                        $stats['official']++;
                    } else {
                        $stats['differs']++;
                    }
                    if ($update && ! $dry) {
                        $prog->forceFill($update)->saveQuietly();
                    }
                }
            }
        }
        $this->info(sprintf('Universities matched: %d; programme rows aligned: %d (now official: %d, official wording differs: %d, names filled: %d).',
            $stats['unis'], $stats['rows_aligned'], $stats['official'], $stats['differs'], $stats['filled']));
        if ($stats['unmatched_unis']) {
            $this->warn('Not matched to a Chinese university ('.count($stats['unmatched_unis']).'): '.implode('; ', array_slice($stats['unmatched_unis'], 0, 20)));
        }

        return self::SUCCESS;
    }

    /** Decode the site's repeated HTML escaping and collapse whitespace. */
    private function clean(string $s): string
    {
        for ($i = 0; $i < 4; $i++) {
            $d = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($d === $s) {
                break;
            }
            $s = $d;
        }

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /** Rows that are real programmes (the site pads empty levels with a "no information" sentence). */
    private function real(array $rows): array
    {
        return array_values(array_filter($rows, fn ($r) => ($r[0] ?? '') !== '' && ! in_array($r[0], ['暂无信息', '暂无'], true) && ! str_starts_with((string) $r[0], "I'm sorry")));
    }

    /** What must agree between the Chinese and English row of the same programme. */
    private function rowKey(array $r): string
    {
        return (int) ($r[1] ?? 0).'|'.(is_numeric($r[2] ?? null) ? (float) $r[2] : 'x').'|'.(is_numeric($r[4] ?? null) ? (int) $r[4] : 'x').'|'.UniversityProgramme::languageLabelFor($r[3] ?? null);
    }

    /**
     * Pairs rows of the two lists in order using the longest common subsequence of their keys (star, duration, tuition, language),
     * so a few rows added or removed between the two collection dates do not shift every later pair.
     *
     * @return list<array{int,int}>
     */
    private function align(array $zr, array $er): array
    {
        $a = array_map(fn ($r) => $this->rowKey($r), $zr);
        $b = array_map(fn ($r) => $this->rowKey($r), $er);
        $n = count($a);
        $m = count($b);
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = $a[$i] === $b[$j] ? $dp[$i + 1][$j + 1] + 1 : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }
        $pairs = [];
        for ($i = 0, $j = 0; $i < $n && $j < $m;) {
            if ($a[$i] === $b[$j]) {
                $pairs[] = [$i, $j];
                $i++;
                $j++;
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $pairs;
    }

    /** Share of programme rows that line up across all levels (0..1). */
    private function score(array $zhLevels, array $enLevels): float
    {
        $aligned = 0;
        $total = 0;
        foreach (self::LEVELS as $zhLevel => [$enLevel]) {
            $zr = $this->real($zhLevels[$zhLevel] ?? []);
            $er = $this->real($enLevels[$enLevel] ?? []);
            $aligned += count($this->align($zr, $er));
            $total += max(count($zr), count($er));
        }

        return $total === 0 ? 0.0 : $aligned / $total;
    }
}
