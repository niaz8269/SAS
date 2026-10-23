<?php

namespace App\Filament\Widgets;

use App\Enums\PublicationState;
use App\Models\ApplicationPlan;
use App\Models\CorrectionReport;
use App\Models\CycleRevision;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\NotificationEvent;
use App\Models\PartnerSubmission;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\ServiceCase;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/** ADM-01. Which tiles a staff member sees depends on their permissions. */
class OperationsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Operations';

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        if ($user->can('content.review') || $user->can('content.draft')) {
            $stats[] = Stat::make('Awaiting review', ScholarshipCycle::where('publication_state', PublicationState::InReview->value)->count())
                ->description('Listings submitted by editors');
            $stats[] = Stat::make('Pending changes to live listings', CycleRevision::where('status', 'pending')->count())
                ->description('Proposed edits waiting for a second person');
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
        if ($user->can('cases.manage') || $user->can('cases.assigned')) {
            $cases = ServiceCase::query()->when(! $user->can('cases.manage'), fn ($q) => $q->where('advisor_id', $user->id));
            $stats[] = Stat::make($user->can('cases.manage') ? 'Open student cases' : 'My open cases', (clone $cases)->whereNotIn('status', ['closed', 'withdrawn', 'rejected', 'accepted'])->count())->description('New, in progress or submitted');
            $stats[] = Stat::make('Waiting for an outcome', (clone $cases)->where('status', 'submitted')->count())->description('Submitted to the funder');
            if ($user->can('cases.manage')) {
                $stats[] = Stat::make('Applications to review', ApplicationPlan::where('stage', 'submitted')->count())->description('Sent by students or agents')->color('warning');
            }
            $stats[] = Stat::make('Students waiting for a reply', (clone $cases)->where('awaiting_reply', true)->count())->description('Open the case and answer under Messages')->color('warning');
            if ($user->can('cases.manage')) {
                $stats[] = Stat::make('Cases without a counsellor', ServiceCase::whereNull('advisor_id')->whereNotIn('status', ['closed', 'withdrawn', 'rejected'])->count())->color('warning');
            }
        }
        if ($user->can('finance.view')) {
            $due = Invoice::where('status', 'issued')->sum('amount_cents');
            $stats[] = Stat::make('Awaiting payment', Invoice::money((int) $due))->description(Invoice::where('status', 'issued')->count().' invoices');
            $stats[] = Stat::make('Refunds due', Invoice::where('refund_status', 'due')->count())->description(Invoice::money((int) Invoice::where('refund_status', 'due')->sum('refund_cents')))->color(Invoice::where('refund_status', 'due')->exists() ? 'danger' : 'success');
        }
        if ($user->can('documents.manage')) {
            $stats[] = Stat::make('Scholarships without document rules', ScholarshipProgramme::whereHas('cycles', fn ($q) => $q->visible())->whereDoesntHave('documentRules')->count())
                ->description('Students get the default document list')->color('warning');
        }
        if ($user->can('partners.manage')) {
            $stats[] = Stat::make('Partner submissions to check', PartnerSubmission::whereIn('status', ['submitted', 'in_review'])->count())->description('Waiting for editors')->color('warning');
        }        if ($user->can('reports.view')) {
            $stats[] = Stat::make('Failed jobs', DB::table('failed_jobs')->count())->color(DB::table('failed_jobs')->count() ? 'danger' : 'success');
            $stats[] = Stat::make('Failed reminders', NotificationEvent::where('status', 'failed')->count());
            $stats[] = Stat::make('Active application plans', ApplicationPlan::whereNotIn('status', ['withdrawn', 'unsuccessful'])->count());
        }

        return $stats ?: [Stat::make('Welcome', 'Nothing assigned to you yet')];
    }
}
