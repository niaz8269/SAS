<?php

namespace App\Domain\Scholarship;

use App\Domain\Communication\ReminderScheduler;
use App\Domain\Editorial\AuditLog;
use App\Enums\DeadlineType;
use App\Models\CycleDeadline;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a deadline as a new immutable version (DEAD-03, SCHEMA-01). The previous row is superseded, not edited.
 * Stale pending reminders are cancelled and replacements scheduled in the same transaction.
 */
class ChangeDeadline
{
    public function __construct(private DeadlineService $service, private ReminderScheduler $reminders) {}

    /**
     * @param array{date_type:string,local_closes_at?:?string,closes_on?:?string,local_opens_at?:?string,opens_on?:?string,source_timezone?:?string,source_text?:?string} $input
     */
    public function __invoke(ScholarshipCycle $cycle, array $input, ?User $actor = null, ?string $reason = null, ?int $routeId = null): CycleDeadline
    {
        $attrs = $this->normalise($input);

        return DB::transaction(function () use ($cycle, $attrs, $actor, $reason, $routeId) {
            $previous = CycleDeadline::where('cycle_id', $cycle->id)->whereNull('superseded_at')
                ->where('route_id', $routeId)->lockForUpdate()->first();

            $version = 1;
            if ($previous) {
                $previous->update(['superseded_at' => now()]);
                $version = $previous->version + 1;
            }

            $new = CycleDeadline::create($attrs + [
                'cycle_id' => $cycle->id, 'route_id' => $routeId, 'version' => $version,
                'changed_by' => $actor?->id, 'change_reason' => $reason,
            ]);

            AuditLog::record('deadline.changed', $cycle, $previous?->only(['date_type', 'closes_at_utc', 'closes_on', 'source_timezone', 'version']),
                $new->only(['date_type', 'closes_at_utc', 'closes_on', 'source_timezone', 'version']), $reason, $actor);

            $cycle->unsetRelation('currentDeadlines');
            $this->service->refresh($cycle);
            $this->reminders->syncCycle($cycle->fresh(['programme']));

            return $new;
        });
    }

    /** Never produces a closing instant for anything but an exact deadline; never invents a 23:59. */
    public function normalise(array $in): array
    {
        $type = DeadlineType::tryFrom($in['date_type'] ?? '') ?? throw ValidationException::withMessages(['date_type' => 'Unknown deadline type.']);
        $tz = $in['source_timezone'] ?? null;
        if ($tz !== null && $tz !== '' && ! in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages(['source_timezone' => 'Use an IANA time zone such as Asia/Shanghai.']);
        }
        $tz = $tz ?: null;

        $attrs = [
            'date_type' => $type->value, 'source_text' => $in['source_text'] ?? null, 'source_timezone' => $tz,
            'closes_at_utc' => null, 'closes_on' => null, 'opens_at_utc' => null, 'opens_on' => null, 'precision' => 'none',
        ];

        if ($type === DeadlineType::Exact) {
            if (blank($in['local_closes_at'] ?? null) || ! $tz) {
                throw ValidationException::withMessages(['local_closes_at' => 'An exact deadline needs a local date-time and its IANA time zone.']);
            }
            $attrs['closes_at_utc'] = CarbonImmutable::parse($in['local_closes_at'], $tz)->utc();
            $attrs['precision'] = 'minute';
            if (! blank($in['local_opens_at'] ?? null)) {
                $attrs['opens_at_utc'] = CarbonImmutable::parse($in['local_opens_at'], $tz)->utc();
            }
        } elseif ($type === DeadlineType::DateOnly) {
            if (blank($in['closes_on'] ?? null)) {
                throw ValidationException::withMessages(['closes_on' => 'A date-only deadline needs the published calendar date.']);
            }
            $attrs['closes_on'] = CarbonImmutable::parse($in['closes_on'])->toDateString();
            $attrs['precision'] = 'day';
        }
        if (! blank($in['opens_on'] ?? null) && $type !== DeadlineType::Exact) {
            $attrs['opens_on'] = CarbonImmutable::parse($in['opens_on'])->toDateString();
        }

        return $attrs;
    }
}
