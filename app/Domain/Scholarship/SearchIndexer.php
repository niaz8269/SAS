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
        $cycle->load(['universities', 'subjects.aliases', 'offerings', 'degreeLevels']);

        // Short, high-value words first (names, subjects, the programmes and majors each scholarship offers), long prose after them, so the length limit only ever cuts prose.
        $parts = [
            $cycle->programme->title, $cycle->programme->funder?->name,
            ...$cycle->subjects->pluck('name')->all(),
            ...$cycle->subjects->flatMap(fn ($s) => $s->aliases->pluck('alias'))->all(),
            ...$cycle->degreeLevels->pluck('name')->all(),
            // Each distinct programme name once (a scholarship can name the same major at hundreds of universities); the long details only for short lists.
            ...$cycle->offerings->pluck('name')->filter()->unique()->all(),
            ...$cycle->offerings->pluck('institution')->filter()->unique()->all(),
            ...($cycle->offerings->count() <= 300 ? $cycle->offerings->pluck('detail')->filter()->all() : []),
            ...$cycle->programme->universities->pluck('name')->all(),
            ...$cycle->universities->pluck('name')->all(),
            Html::text($cycle->programme->summary), Html::text($cycle->summary), Html::text($cycle->programmes_note), Html::text($cycle->institutions_note),
            Html::text($cycle->funding_summary), Html::text($cycle->eligibility_notes),
        ];

        return mb_substr(mb_strtolower(implode(' ', array_filter(array_unique(array_map('strval', array_filter($parts)))))), 0, 80000);
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
