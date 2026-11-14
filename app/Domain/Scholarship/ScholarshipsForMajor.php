<?php

namespace App\Domain\Scholarship;

use App\Domain\Seo\CscGuide;
use App\Models\ScholarshipCycle;
use Illuminate\Support\Collection;

/**
 * For someone who searches degree programmes at universities in China by a subject: the scholarships that can pay for it. Two groups, both from stored data:
 * the scholarships (with a destination in China) whose published details name the subject, and the ones that fund any subject, which are the open-to-any-subject
 * calls and, where programmes in the search are marked open to Chinese Government Scholarship applicants, the Chinese Government Scholarship calls.
 * Calls that have already closed are left out, exactly as in the scholarship search.
 */
class ScholarshipsForMajor
{
    private const DEGREE_SLUG = ['bachelor' => 'bachelor', 'master' => 'master', 'doctorate' => 'doctorate'];

    public function __construct(private readonly ScholarshipSearch $search) {}

    /**
     * @param  string  $q  the subject typed (may be empty)
     * @param  string  $level  a programme level key (bachelor, master, doctorate, ...) or ''
     * @param  bool  $cscProgrammes  whether some programmes in the search are marked open to Chinese Government Scholarship applicants
     * @return array{matched: Collection<int, ScholarshipCycle>, matchedTotal: int, general: Collection<int, ScholarshipCycle>, link: string, degree: ?string}
     */
    public function for(string $q, string $level = '', bool $cscProgrammes = false): array
    {
        $degree = self::DEGREE_SLUG[$level] ?? null;
        // Closed calls are included, after the open ones: a call that has closed still shows a student what exists and what to prepare for the next one.
        $filters = ['destination' => ['china'], 'degree' => $degree ? [$degree] : [], 'show_closed' => true];

        $matchedQuery = $q !== '' ? $this->openFirst($this->search->query($filters + ['q' => $q])) : null;
        $matchedTotal = $matchedQuery ? (clone $matchedQuery)->count() : 0;
        $matched = $matchedQuery ? $matchedQuery->limit(6)->get() : collect();

        // Scholarships that fund any subject: open to any subject, plus the Chinese Government Scholarship calls when the search finds programmes open to them.
        $cscIds = $cscProgrammes ? ($this->csc()['ids'] ?? []) : [];
        $general = $this->openFirst($this->search->query($filters)
            ->where(fn ($w) => $w->where('any_subject', true)->when($cscIds !== [], fn ($x) => $x->orWhereIn('scholarship_cycles.id', $cscIds)))
            ->whereNotIn('scholarship_cycles.id', $matched->pluck('id')))
            ->limit(6)->get();

        return [
            'matched' => $matched, 'matchedTotal' => $matchedTotal, 'general' => $general, 'degree' => $degree,
            'link' => route('scholarships.index', array_filter(['q' => $q ?: null, 'destination' => ['china'], 'degree' => $degree ? [$degree] : null, 'show_closed' => 1])),
        ];
    }

    /** Open and upcoming calls first (soonest closing date first), closed ones after. */
    private function openFirst($query)
    {
        return $query->reorder()
            ->orderByRaw("CASE WHEN application_status = 'closed' OR (next_closing_sort IS NOT NULL AND next_closing_sort < ?) THEN 1 ELSE 0 END", [now()])
            ->orderByRaw('next_closing_sort IS NULL')->orderBy('next_closing_sort')->orderByDesc('scholarship_cycles.id');
    }

    private function csc(): array
    {
        return app(CscGuide::class)->summary() ?? [];
    }
}
