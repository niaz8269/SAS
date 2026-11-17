<?php

namespace App\Filament\Widgets;

use App\Enums\PublicationState;
use App\Models\ApplicationPlan;
use App\Models\CorrectionReport;
use App\Models\CycleRevision;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\NotificationEvent;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\ServiceCase;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ADM-01. Which tiles a staff member sees depends on their permissions. A tile appears only when it has something to say: a count of
 * zero is left out, so the dashboard shows what needs doing instead of a wall of zeros. Things that need action come first.
 */
class OperationsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Operations';

    protected int|array|null $columns = 4;

    protected function getStats(): array
    {
        $user = auth()->user();
        /** @var list<array{0:string,1:int|float,2:?string,3:?string,4:string}> label, number, description, colour, kind (action|info) */
        $rows = [];
        $add = function (string $label, int|float $n, ?string $description = null, ?string $color = null, string $kind = 'action') use (&$rows) {
            $rows[] = [$label, $n, $description, $color, $kind];
        };

        if ($user->can('content.review') || $user->can('content.draft')) {
            $add('Awaiting review', ScholarshipCycle::where('publication_state', PublicationState::InReview->value)->count(), 'Listings submitted by editors');
            $add('Pending changes to live listings', CycleRevision::where('status', 'pending')->count(), 'Waiting for a second person');
            $add('Overdue source checks', ScholarshipCycle::visible()->where('review_due_at', '<', now())->count(), 'Shown as "Needs recheck"', 'warning');
            $add('Closing in 7 days', ScholarshipCycle::visible()->whereBetween('next_closing_sort', [now(), now()->addDays(7)])->count(), 'Confirmed deadlines only');
        }
        if ($user->can('corrections.manage')) {
            $add('Correction reports', CorrectionReport::whereIn('status', ['new', 'reviewing'])->count(), 'Open reports', 'danger');
        }
        if ($user->can('enquiries.view_all')) {
            $add('Unassigned enquiries', Enquiry::whereNull('owner_id')->whereNotIn('status', ['closed', 'converted'])->count(), null, 'warning');
        } elseif ($user->can('enquiries.assigned')) {
            $add('My open enquiries', Enquiry::where('owner_id', $user->id)->whereNotIn('status', ['closed', 'converted'])->count(), null, null, 'info');
        }
        if ($user->can('cases.manage') || $user->can('cases.assigned')) {
            $cases = ServiceCase::query()->when(! $user->can('cases.manage'), fn ($q) => $q->where('advisor_id', $user->id));
            if ($user->can('cases.manage')) {
                $add('Applications to review', ApplicationPlan::where('stage', 'submitted')->count(), 'Sent by students or agents', 'warning');
            }
            $add('Students waiting for a reply', (clone $cases)->where('awaiting_reply', true)->count(), 'Answer under Messages in the case', 'warning');
            if ($user->can('cases.manage')) {
                $add('Cases without a counsellor', ServiceCase::whereNull('advisor_id')->whereNotIn('status', ['closed', 'withdrawn', 'rejected'])->count(), null, 'warning');
            }
            $add($user->can('cases.manage') ? 'Open student cases' : 'My open cases', (clone $cases)->whereNotIn('status', ['closed', 'withdrawn', 'rejected', 'accepted'])->count(), 'New, in progress or submitted', null, 'info');
            $add('Waiting for an outcome', (clone $cases)->where('status', 'submitted')->count(), 'Submitted to the funder', null, 'info');
        }
        if ($user->can('finance.view')) {
            $issued = Invoice::where('status', 'issued');
            $add('Awaiting payment', (int) (clone $issued)->sum('amount_cents') / 100, (clone $issued)->count().' '.Str::plural('invoice', (clone $issued)->count()), null, 'info');
            $refunds = Invoice::where('refund_status', 'due');
            $add('Refunds due', (clone $refunds)->count(), Invoice::money((int) (clone $refunds)->sum('refund_cents')), 'danger');
        }
        if ($user->can('documents.manage')) {
            $add('Scholarships without document rules', ScholarshipProgramme::whereHas('cycles', fn ($q) => $q->visible())->whereDoesntHave('documentRules')->count(), 'Students get the default list', 'warning');
        }
        if ($user->can('reports.view')) {
            $add('Failed jobs', DB::table('failed_jobs')->count(), null, 'danger');
            $add('Failed reminders', NotificationEvent::where('status', 'failed')->count(), null, 'danger');
            $add('Active application plans', ApplicationPlan::whereNotIn('status', ['withdrawn', 'unsuccessful'])->count(), null, null, 'info');
        }

        // Only tiles with something to say; what needs action first, then the running totals. Money is shown as money.
        $rows = array_values(array_filter($rows, fn ($r) => $r[1] > 0));
        usort($rows, fn ($a, $b) => [$a[4] === 'action' ? 0 : 1] <=> [$b[4] === 'action' ? 0 : 1]);
        $stats = [];
        foreach (array_slice($rows, 0, 8) as [$label, $n, $description, $color]) {
            $value = $label === 'Awaiting payment' ? Invoice::money((int) round($n * 100)) : (is_float($n) ? (string) $n : number_format($n));
            $stats[] = Stat::make($label, $value)->description($description)->color($color);
        }

        return $stats ?: [Stat::make('All clear', 'Nothing needs you right now')->color('success')];
    }
}
