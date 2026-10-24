<?php

namespace App\Domain\Admin;

use App\Models\ApplicationPlan;
use App\Models\Invoice;
use App\Models\PartnerOrganisation;
use Illuminate\Support\Collection;

/** Per recruiting agency: people, students, applications by stage, how long reviews have waited, fees owed and last activity. */
class AgentReport
{
    /** @return Collection<int,array<string,mixed>> */
    public function rows(): Collection
    {
        return PartnerOrganisation::where('kind', 'agent')->withCount(['users', 'students'])->orderBy('name')->get()->map(function (PartnerOrganisation $org) {
            $students = $org->students()->pluck('users.id');
            $plans = ApplicationPlan::whereIn('user_id', $students)->get();
            $waiting = $plans->where('stage', 'submitted');
            $owed = Invoice::whereIn('user_id', $students)->where('status', 'issued')->get();

            return [
                'org' => $org, 'people' => $org->users_count, 'students' => $org->students_count,
                'draft' => $plans->where('stage', 'draft')->count(), 'submitted' => $waiting->count(), 'returned' => $plans->where('stage', 'returned')->count(), 'passed' => $plans->where('stage', 'passed')->count(),
                'oldest_review_days' => $waiting->isEmpty() ? null : (int) $waiting->min('review_requested_at')?->diffInDays(now()),
                'owed' => Invoice::money((int) $owed->sum('amount_cents')), 'owed_count' => $owed->count(),
                'last_activity' => $plans->max('updated_at'),
            ];
        });
    }
}
