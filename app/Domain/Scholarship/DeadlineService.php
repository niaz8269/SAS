<?php

namespace App\Domain\Scholarship;

use App\Enums\ApplicationStatus;
use App\Enums\DeadlineType;
use App\Models\CycleDeadline;
use App\Models\ScholarshipCycle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * DEAD-01..04. A closing time is never invented: only `exact` deadlines have a closing instant that is displayed.
 * Date-only deadlines keep a calendar date; an *internal* conservative instant is derived solely to decide when the
 * call can no longer be open (never shown, never used for countdowns).
 */
class DeadlineService
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    /** Internal instant after which the call is certainly closed. Null when nothing is confirmed. */
    public function closeInstant(CycleDeadline $d): ?CarbonImmutable
    {
        return match ($d->date_type) {
            DeadlineType::Exact => $d->closes_at_utc ? CarbonImmutable::instance($d->closes_at_utc)->utc() : null,
            DeadlineType::DateOnly => $d->closes_on ? $this->endOfCalendarDay($d) : null,
            default => null,
        };
    }

    private function endOfCalendarDay(CycleDeadline $d): CarbonImmutable
    {
        $date = $d->closes_on->toDateString();
        if ($d->source_timezone && in_array($d->source_timezone, \DateTimeZone::listIdentifiers(), true)) {
            return CarbonImmutable::parse($date.' 23:59:59.999999', $d->source_timezone)->utc();
        }

        // Zone unknown: the day is not over until it has ended everywhere (UTC-12).
        return CarbonImmutable::parse($date.' 23:59:59', 'UTC')->addHours(12);
    }

    /** @return Collection<int,CycleDeadline> */
    public function current(ScholarshipCycle $cycle): Collection
    {
        return $cycle->relationLoaded('currentDeadlines')
            ? $cycle->currentDeadlines
            : $cycle->currentDeadlines()->get();
    }

    public function deadlineStatus(CycleDeadline $d, ?CarbonInterface $now = null): ApplicationStatus
    {
        $now = $now ? CarbonImmutable::instance($now)->utc() : $this->now();

        if ($d->opens_at_utc && $d->opens_at_utc->gt($now)) {
            return ApplicationStatus::Upcoming;
        }
        if ($d->opens_on && $d->opens_on->toDateString() > $now->toDateString()) {
            return ApplicationStatus::Upcoming;
        }

        return match ($d->date_type) {
            DeadlineType::Exact, DeadlineType::DateOnly => ($close = $this->closeInstant($d)) === null
                ? ApplicationStatus::DeadlineUnconfirmed
                : ($close->lte($now) ? ApplicationStatus::Closed : ApplicationStatus::Open),
            DeadlineType::Rolling => ApplicationStatus::Rolling,
            default => ApplicationStatus::DeadlineUnconfirmed,
        };
    }

    public function statusFor(ScholarshipCycle $cycle, ?CarbonInterface $now = null): ApplicationStatus
    {
        if ($cycle->application_status === ApplicationStatus::Suspended) {
            return ApplicationStatus::Suspended;
        }

        $statuses = $this->current($cycle)->map(fn ($d) => $this->deadlineStatus($d, $now));
        if ($statuses->isEmpty()) {
            return ApplicationStatus::DeadlineUnconfirmed;
        }

        foreach ([ApplicationStatus::Open, ApplicationStatus::Rolling, ApplicationStatus::Upcoming, ApplicationStatus::DeadlineUnconfirmed] as $candidate) {
            if ($statuses->contains($candidate)) {
                return $candidate;
            }
        }

        return ApplicationStatus::Closed;
    }

    /** Earliest confirmed closing instant that is still in the future. */
    public function nextClosing(ScholarshipCycle $cycle, ?CarbonInterface $now = null): ?CarbonImmutable
    {
        $now = $now ? CarbonImmutable::instance($now)->utc() : $this->now();

        return $this->current($cycle)
            ->map(fn ($d) => $this->closeInstant($d))
            ->filter(fn ($i) => $i !== null && $i->gt($now))
            ->sort()
            ->first();
    }

    /** The deadline that should headline the cycle: earliest future confirmed one, otherwise the first current one. */
    public function headline(ScholarshipCycle $cycle, ?CarbonInterface $now = null): ?CycleDeadline
    {
        $now = $now ? CarbonImmutable::instance($now)->utc() : $this->now();
        $current = $this->current($cycle);

        return $current
            ->filter(fn ($d) => ($i = $this->closeInstant($d)) !== null && $i->gt($now))
            ->sortBy(fn ($d) => $this->closeInstant($d)->getTimestamp())
            ->first() ?? $current->first();
    }

    /** "Closing soon" admits only confirmed, still-future closings (UAT-03). */
    public function isClosingSoon(ScholarshipCycle $cycle, ?int $days = null, ?CarbonInterface $now = null): bool
    {
        $days ??= (int) config('sas.deadlines.closing_soon_days', 30);
        $now = $now ? CarbonImmutable::instance($now)->utc() : $this->now();
        $next = $this->nextClosing($cycle, $now);

        return $next !== null && $next->lte($now->addDays($days)) && $this->statusFor($cycle, $now) === ApplicationStatus::Open;
    }

    /**
     * Presentation model. `countdown` is true only for exact, future deadlines (DEAD-02).
     *
     * @return array{type:string,primary:string,secondary:?string,note:?string,countdown:bool,closes_at:?string,closed:bool,confirmed_time:bool}
     */
    public function describe(CycleDeadline $d, ?string $viewerTimezone = null, ?CarbonInterface $now = null): array
    {
        $now = $now ? CarbonImmutable::instance($now)->utc() : $this->now();
        $closed = $this->deadlineStatus($d, $now) === ApplicationStatus::Closed;
        $out = ['type' => $d->date_type->value, 'primary' => '', 'secondary' => null, 'note' => null,
            'countdown' => false, 'closes_at' => null, 'closed' => $closed, 'confirmed_time' => false];

        switch ($d->date_type) {
            case DeadlineType::Exact:
                $tz = $this->validZone($d->source_timezone) ?? 'UTC';
                $at = CarbonImmutable::instance($d->closes_at_utc)->setTimezone($tz);
                $out['primary'] = $at->format('j F Y, H:i').' ('.$tz.')';
                $out['confirmed_time'] = true;
                $out['closes_at'] = $at->utc()->toIso8601String();
                $out['countdown'] = ! $closed;
                $viewer = $this->validZone($viewerTimezone);
                if ($viewer && $viewer !== $tz) {
                    $out['secondary'] = $at->setTimezone($viewer)->format('j F Y, H:i').' your time ('.$viewer.')';
                }
                break;
            case DeadlineType::DateOnly:
                $out['primary'] = $d->closes_on->format('j F Y');
                $out['note'] = 'Closing time not confirmed';
                break;
            case DeadlineType::Rolling:
                $out['primary'] = 'Rolling admissions';
                $out['note'] = 'No fixed closing date published';
                break;
            case DeadlineType::RouteDependent:
                $out['primary'] = 'Varies by route';
                $out['note'] = 'Check each application route';
                break;
            default:
                $out['primary'] = 'Not announced';
                $out['note'] = 'Closing date not confirmed';
        }

        return $out;
    }

    /** Re-evaluates the stored columns used for filtering/sorting. Safe to call repeatedly. */
    public function refresh(ScholarshipCycle $cycle): ScholarshipCycle
    {
        $cycle->unsetRelation('currentDeadlines');
        $now = $this->now();

        if ($cycle->application_status !== ApplicationStatus::Suspended) {
            $cycle->application_status = $this->statusFor($cycle, $now);
        }
        $cycle->next_closing_sort = $this->nextClosing($cycle, $now);
        $cycle->save();

        return $cycle;
    }

    private function validZone(?string $tz): ?string
    {
        return $tz && in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : null;
    }
}
