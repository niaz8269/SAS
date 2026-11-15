<?php

namespace App\Domain\Catalogue;

use App\Models\ScholarshipCycle;
use App\Models\UniversityProgramme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * The programmes that the Study in China system lists as open to Chinese Government Scholarship applicants (about 54,800 at 290 universities, held in
 * university_programmes with csc_eligible), as the programme list of the Chinese Government Scholarship calls. Which route (Type A, Type B, Silk Road)
 * applies to a programme depends on the university and the applicant's country, so each call page says so; the catalogue itself never claims more
 * than "listed as open to CGS applicants".
 */
class CscCatalogue
{
    /** Scholarship page level (slug) => programme level in the catalogue. */
    private const LEVELS = ['bachelor' => 'bachelor', 'master' => 'master', 'doctorate' => 'doctorate', 'diploma' => 'non_degree'];

    /** Is this call one of the Chinese Government Scholarship calls (so its programmes are the catalogue)? */
    public static function appliesTo(ScholarshipCycle $cycle): bool
    {
        return str_starts_with((string) $cycle->programme?->title, 'Chinese Government Scholarship');
    }

    public function query(string $q = '', string $levelSlug = ''): Builder
    {
        $like = fn (string $s) => '%'.addcslashes($s, '%_\\').'%';

        return UniversityProgramme::query()->public()->where('csc_eligible', true)
            ->when(isset(self::LEVELS[$levelSlug]), fn ($w) => $w->where('level', self::LEVELS[$levelSlug]))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name_en', 'like', $like($q))->orWhere('name_zh', 'like', $like($q))
                ->orWhereHas('university', fn ($u) => $u->where('name', 'like', $like($q)))));
    }

    /** @return array<string,int> programmes per scholarship-page level slug (plain numbers: safe to cache) */
    public function counts(): array
    {
        return Cache::remember('csc.catalogue.counts', 3600, function () {
            $byLevel = UniversityProgramme::query()->public()->where('csc_eligible', true)->selectRaw('level, count(*) as n')->groupBy('level')->pluck('n', 'level');
            $out = [];
            foreach (self::LEVELS as $slug => $level) {
                $out[$slug] = (int) ($byLevel[$level] ?? 0);
            }

            return $out;
        });
    }

    public function universities(): int
    {
        return (int) Cache::remember('csc.catalogue.universities', 3600, fn () => UniversityProgramme::query()->public()->where('csc_eligible', true)->distinct('university_id')->count('university_id'));
    }

    /** The level names used on the pages. */
    public static function levelNames(): array
    {
        return ['bachelor' => 'Bachelor', 'master' => "Master's", 'doctorate' => 'PhD / Doctorate', 'diploma' => 'Non-degree'];
    }
}
