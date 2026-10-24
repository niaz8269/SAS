<?php

namespace App\Domain\Scholarship;

use App\Models\ScholarshipCycle;
use App\Support\Html;
use Illuminate\Support\Facades\DB;

/**
 * Keeps cycle_search_index.search_text: one lower-cased string holding everything keyword search covers (DIR-02): programme title
 * and summary, funder, host institutions, subjects and their aliases, and the cycle summary. Rebuilt when a cycle is published,
 * edited, imported or revised, and nightly (`sas:reindex-search`) to catch renamed funders, universities and subject aliases.
 */
class SearchIndexer
{
    public function text(ScholarshipCycle $cycle): string
    {
        $cycle->loadMissing(['programme.funder', 'programme.universities']);
        $cycle->load(['universities', 'subjects.aliases']);

        $parts = [
            $cycle->programme->title, Html::text($cycle->programme->summary), $cycle->programme->funder?->name,
            Html::text($cycle->summary), Html::text($cycle->funding_summary), Html::text($cycle->eligibility_notes), Html::text($cycle->programmes_note),
            ...$cycle->programme->universities->pluck('name')->all(),
            ...$cycle->universities->pluck('name')->all(),
            ...$cycle->subjects->pluck('name')->all(),
            ...$cycle->subjects->flatMap(fn ($s) => $s->aliases->pluck('alias'))->all(),
        ];

        return mb_substr(mb_strtolower(implode(' ', array_filter(array_unique(array_map('strval', array_filter($parts)))))), 0, 8000);
    }

    public function reindex(ScholarshipCycle $cycle): void
    {
        DB::table('cycle_search_index')->updateOrInsert(['cycle_id' => $cycle->id], ['search_text' => $this->text($cycle)]);
    }

    public function reindexAll(): int
    {
        $n = 0;
        ScholarshipCycle::query()->chunkById(200, function ($cycles) use (&$n) {
            foreach ($cycles as $cycle) {
                $this->reindex($cycle);
                $n++;
            }
        });

        return $n;
    }
}
