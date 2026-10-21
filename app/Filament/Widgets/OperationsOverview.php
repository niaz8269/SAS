<?php

namespace App\Filament\Widgets;

use App\Enums\PublicationState;
use App\Models\ApplicationPlan;
use App\Models\CorrectionReport;
use App\Models\Enquiry;
use App\Models\NotificationEvent;
use App\Models\ScholarshipCycle;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/** ADM-01. Which tiles a staff member sees depends on their permissions. */
class OperationsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Operations';

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        if ($user->can('content.review') || $user->can('content.draft')) {
            $stats[] = Stat::make('Awaiting review', ScholarshipCycle::where('publication_state', PublicationState::InReview->value)->count())
                ->description('Listings submitted by editors');
            $stats[] = Stat::make('Overdue source checks', ScholarshipCycle::visible()->where('review_due_at', '<', now())->count())
                ->description('Shown as "Needs recheck" and not promoted')->color('warning');
            $stats[] = Stat::make('Closing in 7 days', ScholarshipCycle::visible()->whereBetween('next_closing_sort', [now(), now()->addDays(7)])->count())
                ->description('Confirmed deadlines only');
        }
        if ($user->can('corrections.manage')) {
            $stats[] = Stat::make('Correction reports', CorrectionReport::whereIn('status', ['new', 'reviewing'])->count())->description('Open reports')->color('danger');
        }
        if ($user->can('enquiries.view_all')) {
            $stats[] = Stat::make('Unassigned enquiries', Enquiry::whereNull('owner_id')->whereNotIn('status', ['closed', 'converted'])->count())->color('warning');
            $answered = Enquiry::whereNotNull('first_response_at')->where('created_at', '>', now()->subDays(30))->get();
            $stats[] = Stat::make('Avg first response (30 d)', $answered->isEmpty() ? 'n/a' : round($answered->avg(fn ($e) => $e->created_at->diffInMinutes($e->first_response_at)) / 60, 1).' h');
        } elseif ($user->can('enquiries.assigned')) {
            $stats[] = Stat::make('My open enquiries', Enquiry::where('owner_id', $user->id)->whereNotIn('status', ['closed', 'converted'])->count());
        }
        if ($user->can('reports.view')) {
            $stats[] = Stat::make('Failed jobs', DB::table('failed_jobs')->count())->color(DB::table('failed_jobs')->count() ? 'danger' : 'success');
            $stats[] = Stat::make('Failed reminders', NotificationEvent::where('status', 'failed')->count());
            $stats[] = Stat::make('Active application plans', ApplicationPlan::whereNotIn('status', ['withdrawn', 'unsuccessful'])->count());
        }

        return $stats ?: [Stat::make('Welcome', 'Nothing assigned to you yet')];
    }
}
