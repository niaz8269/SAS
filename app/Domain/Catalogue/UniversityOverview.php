<?php

namespace App\Domain\Catalogue;

use App\Models\University;
use App\Models\UniversityProgramme;
use Illuminate\Support\Collection;

/**
 * A short plain-language summary of a university page, written only from what is stored about its published programmes
 * (counts by degree level, teaching languages, listed tuition and length, how many are marked open to Chinese Government Scholarship applicants)
 * and the other universities in the same city or province. Nothing is estimated: a fact that is not stored is left out, and with no programmes
 * there is no summary at all.
 */
class UniversityOverview
{
    /** @return array{paragraphs: list<string>, nearby: Collection<int, University>, nearbyIn: string}|null */
    public function for(University $university, string $place): ?array
    {
        $rows = $university->programmes()->where('published', true)->get(['level', 'name_en', 'name_zh', 'teaching_language', 'csc_eligible', 'duration_years', 'tuition_cny']);
        $nearby = $this->nearby($university);
        $nearbyIn = $university->city ?: (string) $university->province;

        if ($rows->isEmpty()) {
            return $nearby->isEmpty() ? null : ['paragraphs' => [], 'nearby' => $nearby, 'nearbyIn' => $nearbyIn];
        }

        $paragraphs = [];
        $country = $university->country?->name ?: 'China';
        $byLevel = $rows->countBy('level');
        $levels = collect(UniversityProgramme::LEVELS)->filter(fn ($label, $key) => $byLevel->has($key))
            ->map(fn ($label, $key) => number_format($byLevel[$key]).' '.mb_strtolower($label))->values()->all();
        $paragraphs[] = $university->name.' is a university'.($place !== '' ? ' in '.$place : '').', '.$country.'. The study-in-China programme list that SAS follows shows '
            .number_format($rows->count()).' '.str('programme')->plural($rows->count()).' for it'.($levels ? ': '.$this->join($levels) : '').'.';

        $byLanguage = $rows->filter(fn ($r) => filled($r->teaching_language))->countBy(fn ($r) => UniversityProgramme::languageLabelFor($r->teaching_language))->sortDesc();
        if ($byLanguage->isNotEmpty()) {
            $parts = $byLanguage->map(fn ($n, $label) => $label.' ('.number_format($n).')')->values()->all();
            $paragraphs[] = 'Teaching language: '.$this->join($parts).'.';
        }

        $fees = $rows->pluck('tuition_cny')->filter(fn ($v) => $v !== null && $v > 0);
        if ($fees->isNotEmpty()) {
            $paragraphs[] = $fees->min() === $fees->max()
                ? 'Listed tuition is CNY '.number_format($fees->min()).' a year.'
                : 'Listed tuition runs from CNY '.number_format($fees->min()).' to CNY '.number_format($fees->max()).' a year, depending on the programme ('.number_format($fees->count()).' of the '.number_format($rows->count()).' programmes list a fee).';
        }

        $years = $rows->pluck('duration_years')->filter(fn ($v) => $v !== null && $v > 0);
        if ($years->isNotEmpty()) {
            $fmt = fn ($v) => rtrim(rtrim(number_format($v, 1), '0'), '.');
            $paragraphs[] = $years->min() === $years->max()
                ? 'Programmes last '.$fmt($years->min()).' '.str('year')->plural($years->min() == 1 ? 1 : 2).'.'
                : 'Programmes last from '.$fmt($years->min()).' to '.$fmt($years->max()).' years.';
        }

        $csc = $rows->where('csc_eligible', true)->count();
        if ($csc > 0) {
            $paragraphs[] = number_format($csc).' of these '.str('programme')->plural($rows->count()).' '.($csc === 1 ? 'is' : 'are').' marked as open to Chinese Government Scholarship applicants (the star in the list below). Check the scholarship pages on SAS for the intake and deadline.';
        }

        $names = $rows->map(fn ($r) => $r->name_en ?: $r->name_zh)->filter()->unique()->values();
        if ($names->count() >= 3) {
            $step = max(1, intdiv($names->count(), 6));
            $sample = $names->filter(fn ($n, $i) => $i % $step === 0)->take(6)->values()->all();
            $paragraphs[] = 'Examples from the list: '.implode('; ', $sample).'.';
        }

        return ['paragraphs' => $paragraphs, 'nearby' => $nearby, 'nearbyIn' => $nearbyIn];
    }

    /** Up to eight other catalogue universities in the same city (or, without one, the same province). */
    private function nearby(University $university): Collection
    {
        $column = filled($university->city) ? 'city' : (filled($university->province) ? 'province' : null);
        if ($column === null) {
            return collect();
        }

        return University::inCatalogue()->where($column, $university->{$column})->where('id', '!=', $university->id)->orderBy('name')->limit(8)->get(['id', 'name', 'slug']);
    }

    private function join(array $items): string
    {
        $last = array_pop($items);

        return $items ? implode(', ', $items).' and '.$last : (string) $last;
    }
}
