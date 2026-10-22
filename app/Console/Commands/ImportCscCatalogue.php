<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\University;
use App\Models\UniversityProgramme;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports the university + programme catalogue collected from the official study-in-China system (campuschina.org).
 * Input is a JSON file: [{"loc":"北京--北京","name":"北京大学","key":"…","levels":{"本科":[["专业",1,"4","汉语","26000"]],…}}].
 * Facts only (names, location, duration, language, tuition, CSC-eligible flag). Safe to re-run: rows are upserted.
 */
class ImportCscCatalogue extends Command
{
    protected $signature = 'sas:import-csc-catalogue {file : JSON file} {--checked= : date the source was read (YYYY-MM-DD), default today}';

    protected $description = 'Import universities and degree programmes collected from the official study-in-China system.';

    private const LEVELS = ['本科' => 'bachelor', '硕研' => 'master', '博研' => 'doctorate', '非学历' => 'non_degree', '专科' => 'associate'];

    private const PLACEHOLDERS = ['暂无信息', '暂无', '无'];

    public function handle(): int
    {
        $file = $this->argument('file');
        $data = is_file($file) ? json_decode(file_get_contents($file), true) : null;
        if (! is_array($data)) {
            $this->error('Could not read a JSON array from the file.');

            return self::FAILURE;
        }
        $china = Country::where('code', 'CN')->firstOrFail();
        $checked = $this->option('checked') ? CarbonImmutable::parse($this->option('checked')) : now();
        $unis = $progs = $skipped = 0;

        foreach ($data as $row) {
            $nameZh = trim((string) ($row['name'] ?? ''));
            if ($nameZh === '') {
                $skipped++;

                continue;
            }
            [$province, $city] = $this->location((string) ($row['loc'] ?? ''));
            $key = ! empty($row['key']) ? (string) $row['key'] : null;

            DB::transaction(function () use ($row, $nameZh, $province, $city, $key, $china, $checked, &$unis, &$progs) {
                $uni = University::where('name_zh', $nameZh)->first() ?? new University;
                if (! $uni->exists) {
                    $uni->name = $nameZh; // English name still to be added by an editor
                    $uni->slug = 'csc-'.substr(sha1($nameZh), 0, 10);
                    $uni->name_zh = $nameZh;
                }
                $uni->fill(['country_id' => $china->id, 'city' => $city])->forceFill([
                    'province' => $province, 'csc_key' => $key ?? $uni->csc_key,
                    'source_url' => ($key ?? $uni->csc_key) ? 'https://www.campuschina.org/zh/universities_art/index.html?key='.($key ?? $uni->csc_key) : 'https://www.campuschina.org/zh/universities/index.html',
                    'catalogue_checked_at' => $checked,
                ])->save();
                $unis++;

                foreach ($row['levels'] ?? [] as $zhLevel => $items) {
                    $level = self::LEVELS[$zhLevel] ?? null;
                    if (! $level) {
                        continue;
                    }
                    foreach ($items as $item) {
                        $name = trim((string) ($item[0] ?? ''));
                        if ($name === '' || in_array($name, self::PLACEHOLDERS, true)) {
                            continue; // "no information" placeholder rows
                        }
                        $lang = trim((string) ($item[3] ?? '')) ?: null;
                        UniversityProgramme::updateOrCreate(
                            ['university_id' => $uni->id, 'level' => $level, 'name_zh' => $name, 'teaching_language' => $lang],
                            ['csc_eligible' => (bool) ($item[1] ?? 0), 'duration_years' => is_numeric($item[2] ?? null) ? (float) $item[2] : null,
                                'tuition_cny' => is_numeric($item[4] ?? null) ? (int) $item[4] : null],
                        );
                        $progs++;
                    }
                }
            });
        }

        $this->info("Universities processed: {$unis}; programme rows processed: {$progs}; skipped: {$skipped}.");

        return self::SUCCESS;
    }

    /** "北京--北京", "广东--广州", "广州、珠海、深圳", "南宁市" -> [province|null, city]. */
    private function location(string $loc): array
    {
        $parts = preg_split('/\s*[-－—]{1,3}\s*/u', trim($loc));
        if (count($parts) >= 2) {
            return [Str::limit($parts[0], 60, ''), Str::limit(implode(', ', array_slice($parts, 1)), 120, '')];
        }

        return [null, Str::limit($loc, 120, '')];
    }
}
