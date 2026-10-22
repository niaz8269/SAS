<?php

namespace App\Console\Commands;

use App\Models\University;
use App\Models\UniversityProgramme;
use Illuminate\Console\Command;

/**
 * Applies English names to the China catalogue from a JSON map {"中文名": "English name"}.
 * Never overwrites a name staff typed in the admin or one marked official by a lower-trust source.
 */
class ImportEnglishNames extends Command
{
    protected $signature = 'sas:import-english-names {file : JSON object chinese => english} {--type= : universities or programmes} {--source=translated : official or translated}';

    protected $description = 'Set English names on universities or programmes from a JSON map.';

    public function handle(): int
    {
        $map = is_file($this->argument('file')) ? json_decode(file_get_contents($this->argument('file')), true) : null;
        $type = $this->option('type');
        $source = $this->option('source');
        if (! is_array($map) || ! in_array($type, ['universities', 'programmes'], true) || ! in_array($source, ['official', 'translated'], true)) {
            $this->error('Need a JSON object file, --type=universities|programmes and --source=official|translated.');

            return self::FAILURE;
        }

        $updated = $skipped = 0;
        foreach ($map as $zh => $en) {
            $en = trim((string) $en);
            if ($en === '' || preg_match('/\p{Han}/u', $en)) {
                $skipped++;

                continue;
            }
            $rank = ['translated' => 1, 'official' => 2, 'staff' => 3];
            if ($type === 'universities') {
                $rows = University::where('name_zh', $zh)->get();
                foreach ($rows as $u) {
                    if (($rank[$u->name_en_source] ?? 0) > $rank[$source]) {
                        $skipped++;

                        continue;
                    }
                    $u->forceFill(['name' => $en, 'name_en_source' => $source])->save();
                    $updated++;
                }
            } else {
                $rows = UniversityProgramme::where('name_zh', $zh)->get(['id', 'name_en_source']);
                $ids = $rows->filter(fn ($p) => ($rank[$p->name_en_source] ?? 0) <= $rank[$source])->pluck('id');
                $updated += UniversityProgramme::whereIn('id', $ids)->update(['name_en' => $en, 'name_en_source' => $source]);
                $skipped += $rows->count() - $ids->count();
            }
        }
        $this->info("Updated: {$updated}; skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
