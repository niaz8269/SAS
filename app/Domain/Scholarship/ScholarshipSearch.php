<?php

namespace App\Domain\Scholarship;

use App\Enums\ApplicationStatus;
use App\Enums\PublicationState;
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
            foreach (preg_split('/\s+/', $f['q']) as $term) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
                $q->where(function (Builder $w) use ($like) {
                    $w->whereHas('programme', fn ($p) => $p->where('title', 'like', $like)->orWhere('summary', 'like', $like)
                            ->orWhereHas('funder', fn ($x) => $x->where('name', 'like', $like))
                            ->orWhereHas('universities', fn ($x) => $x->where('name', 'like', $like)))
                        ->orWhereHas('universities', fn ($x) => $x->where('name', 'like', $like))
                        ->orWhereHas('subjects', fn ($x) => $x->where('name', 'like', $like)
                            ->orWhereHas('aliases', fn ($a) => $a->where('alias', 'like', $like)))
                        ->orWhere('scholarship_cycles.summary', 'like', $like);
                });
            }
        }

        if ($f['destination']) {
            $q->whereHas('destinations', fn ($x) => $x->whereIn('countries.slug', $f['destination']));
        }
        if ($f['nationality']) {
            // "Any nationality" is explicit data. Calls whose eligible nationalities are unconfirmed are not matched.
            $q->where(fn ($w) => $w->where('any_nationality', true)
                ->orWhereHas('nationalities', fn ($x) => $x->whereIn('countries.slug', $f['nationality'])));
        }
        if ($f['degree']) {
            $q->whereHas('degreeLevels', fn ($x) => $x->whereIn('degree_levels.slug', $f['degree']));
        }
        if ($f['subject']) {
            $q->whereHas('subjects', fn ($x) => $x->whereIn('subjects.slug', $f['subject']));
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
            $q->whereHas('currentDeadlines', function ($d) use ($f) {
                $d->whereIn('date_type', ['exact', 'date_only']);
                if ($f['deadline_from']) {
                    $d->where(fn ($x) => $x->where('closes_at_utc', '>=', $f['deadline_from'].' 00:00:00')->orWhere('closes_on', '>=', $f['deadline_from']));
                }
                if ($f['deadline_to']) {
                    $d->where(fn ($x) => $x->where('closes_at_utc', '<=', $f['deadline_to'].' 23:59:59')->orWhere('closes_on', '<=', $f['deadline_to']));
                }
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

        return $q->orderByRaw('featured DESC')->orderByRaw('next_closing_sort IS NULL')->orderBy('next_closing_sort')->orderByDesc('id');
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