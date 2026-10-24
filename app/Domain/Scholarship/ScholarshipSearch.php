<?php

namespace App\Domain\Scholarship;

use App\Enums\ApplicationStatus;
use App\Models\ScholarshipCycle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * DIR-01..03. Values inside one filter are OR-ed, different filters are AND-ed. Only published cycles are ever searched,
 * so drafts cannot leak. Replace the internals with a Scout adapter in R3 without touching callers.
 */
class ScholarshipSearch
{
    public const SORTS = ['relevance', 'closing', 'newest', 'verified'];

    /** @param array<string,mixed> $f */
    public function query(array $f): Builder
    {
        $f = $this->clean($f);
        $q = ScholarshipCycle::query()->visible()
            ->with(['programme.funder', 'destinations', 'degreeLevels', 'currentDeadlines', 'universities', 'programme.universities']);

        if ($f['q'] !== '') {
            // One EXISTS against the narrow index table; every term must appear (AND).
            $terms = preg_split('/\s+/', mb_strtolower($f['q']));
            $q->whereExists(function ($s) use ($terms) {
                $s->selectRaw('1')->from('cycle_search_index')->whereColumn('cycle_search_index.cycle_id', 'scholarship_cycles.id');
                foreach ($terms as $term) {
                    $s->where('cycle_search_index.search_text', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%');
                }
            });
        }
        if ($f['destination']) {
            $q->whereHas('destinations', fn ($x) => $x->whereIn('countries.slug', $f['destination']));
        }
        if ($f['nationality']) {
            // "Any nationality" is explicit data. Calls whose eligible nationalities are unconfirmed are not matched.
            $q->where(fn ($w) => $w->where('any_nationality', true)
                ->orWhereHas('nationalities', fn ($x) => $x->whereIn('countries.slug', $f['nationality'])))
                ->whereDoesntHave('excludedNationalities', fn ($x) => $x->whereIn('countries.slug', $f['nationality'])); // open to all, but not to this one
        }
        if ($f['degree']) {
            $q->whereHas('degreeLevels', fn ($x) => $x->whereIn('degree_levels.slug', $f['degree']));
        }
        if ($f['subject']) {
            // A call open to any subject belongs in every subject search; one with no subject data is not matched.
            $q->where(fn ($w) => $w->where('any_subject', true)->orWhereHas('subjects', fn ($x) => $x->whereIn('subjects.slug', $f['subject'])));
        }
        if ($f['funding']) {
            $q->where(function (Builder $w) use ($f) {
                foreach ($f['funding'] as $type) {
                    $type === 'full'
                        ? $w->orWhere(fn ($x) => $x->where('funding_type', 'full')->where('fully_funded_verified', true))
                        : $w->orWhere('funding_type', $type);
                }
            });
        }
        if ($f['language']) {
            $q->whereIn('teaching_language', $f['language']);
        }
        if ($f['mode']) {
            $q->whereIn('delivery_mode', $f['mode']);
        }
        if ($f['intake']) {
            $q->whereIn('intake_year', $f['intake']);
        }
        if ($f['status']) {
            $q->whereIn('application_status', $f['status']);
        }
        if ($f['deadline_from'] || $f['deadline_to']) {
            // Two index-friendly branches instead of one OR-ed predicate: exact instants and calendar dates.
            $q->where(function (Builder $w) use ($f) {
                $w->whereHas('currentDeadlines', function ($d) use ($f) {
                    $d->where('date_type', 'exact')
                        ->when($f['deadline_from'], fn ($x) => $x->where('closes_at_utc', '>=', $f['deadline_from'].' 00:00:00'))
                        ->when($f['deadline_to'], fn ($x) => $x->where('closes_at_utc', '<=', $f['deadline_to'].' 23:59:59'));
                })->orWhereHas('currentDeadlines', function ($d) use ($f) {
                    $d->where('date_type', 'date_only')
                        ->when($f['deadline_from'], fn ($x) => $x->where('closes_on', '>=', $f['deadline_from']))
                        ->when($f['deadline_to'], fn ($x) => $x->where('closes_on', '<', date('Y-m-d', strtotime($f['deadline_to'].' +1 day')))); // exclusive next day: correct for DATE columns and SQLite's datetime strings
                });
            });
        }

        if (! $f['show_closed']) {
            // Default view: open, upcoming, rolling and unconfirmed calls. A call whose time has passed is hidden even if the
            // scheduled status refresh has not run yet.
            $q->where('application_status', '!=', ApplicationStatus::Closed->value)
                ->where(fn ($w) => $w->whereNull('next_closing_sort')->orWhere('next_closing_sort', '>', now()));
        }

        return $this->sort($q, $f);
    }

    public function paginate(array $f, int $perPage = 12): LengthAwarePaginator
    {
        return $this->query($f)->paginate($perPage)->withQueryString();
    }

    private function sort(Builder $q, array $f): Builder
    {
        return match ($f['sort']) {
            'closing' => $q->orderByRaw('next_closing_sort IS NULL')->orderBy('next_closing_sort'), // unknown deadlines last
            'newest' => $q->orderByDesc('published_at'),
            'verified' => $q->orderByDesc('last_verified_at'),
            default => $this->relevance($q, $f['q']),
        };
    }

    private function relevance(Builder $q, string $term): Builder
    {
        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
            $q->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM scholarship_programmes sp WHERE sp.id = scholarship_cycles.programme_id AND sp.title LIKE ?) THEN 0 ELSE 1 END', [$like]);
        }

        // staff-placed order first (drag and drop in the admin), then featured, then the soonest deadline
        return $q->manualOrder()->orderByRaw('featured DESC')->orderByRaw('next_closing_sort IS NULL')->orderBy('next_closing_sort')->orderByDesc('id');
    }

    /** @return array<string,mixed> */
    public function clean(array $f): array
    {
        $list = fn ($v) => array_values(array_filter((array) $v, fn ($x) => is_scalar($x) && trim((string) $x) !== ''));
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;

        return [
            'q' => trim((string) ($f['q'] ?? '')),
            'destination' => $list($f['destination'] ?? []),
            'nationality' => $list($f['nationality'] ?? []),
            'degree' => $list($f['degree'] ?? []),
            'subject' => $list($f['subject'] ?? []),
            'funding' => array_values(array_intersect($list($f['funding'] ?? []), ['full', 'partial', 'tuition_only', 'stipend_only', 'other'])),
            'language' => $list($f['language'] ?? []),
            'mode' => array_values(array_intersect($list($f['mode'] ?? []), ['on_campus', 'online', 'hybrid'])),
            'intake' => array_map('intval', $list($f['intake'] ?? [])),
            'status' => array_values(array_intersect($list($f['status'] ?? []), array_column(ApplicationStatus::cases(), 'value'))),
            'deadline_from' => $date($f['deadline_from'] ?? null),
            'deadline_to' => $date($f['deadline_to'] ?? null),
            'show_closed' => filter_var($f['show_closed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sort' => in_array($f['sort'] ?? '', self::SORTS, true) ? $f['sort'] : 'relevance',
        ];
    }
}
