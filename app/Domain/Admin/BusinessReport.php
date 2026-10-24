<?php

namespace App\Domain\Admin;

use App\Enums\EnquiryStatus;
use App\Models\ApplicationPlan;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\PartnerOrganisation;
use App\Models\ServiceCase;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** The numbers an owner asks for, for a date range. Every section is a title, headings and rows, so it shows on screen and exports as CSV the same way. */
class BusinessReport
{
    public const SECTIONS = ['enquiries', 'students', 'cases', 'scholarships', 'countries', 'money', 'agents'];

    /** @return array<string,array{title:string,headers:list<string>,rows:list<list<string|int|float>>}> */
    public function sections(CarbonInterface $from, CarbonInterface $to): array
    {
        return collect(self::SECTIONS)->mapWithKeys(fn (string $key) => [$key => $this->section($key, $from, $to)])->all();
    }

    /** @return array{title:string,headers:list<string>,rows:list<list<string|int|float>>} */
    public function section(string $key, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        return match ($key) {
            'enquiries' => $this->enquiries($from, $to),
            'students' => $this->students($from, $to),
            'cases' => $this->cases($from, $to),
            'scholarships' => $this->scholarships($from, $to),
            'countries' => $this->countries($from, $to),
            'money' => $this->money($from, $to),
            'agents' => $this->agents($from, $to),
            default => abort(404),
        };
    }

    private function enquiries($from, $to): array
    {
        $rows = Enquiry::whereBetween('created_at', [$from, $to])->get()->groupBy('category')->map(function (Collection $g, $category) {
            $answered = $g->whereNotNull('first_response_at');

            return [config('sas.enquiry_categories.'.$category, $category), $g->count(), $g->filter(fn ($e) => $e->status === EnquiryStatus::Converted)->count(), $g->filter(fn ($e) => $e->status === EnquiryStatus::Closed)->count(),
                $answered->isEmpty() ? '-' : round($answered->avg(fn ($e) => $e->created_at->diffInMinutes($e->first_response_at)) / 60, 1)];
        })->values()->all();

        return ['title' => 'Enquiries received, by kind', 'headers' => ['Kind', 'Received', 'Became a case', 'Closed', 'Average first reply (hours)'], 'rows' => $rows];
    }

    private function students($from, $to): array
    {
        $new = Student::whereBetween('users.created_at', [$from, $to]);
        $assigned = (clone $new)->where(fn ($q) => $q->whereNotNull('counsellor_id')->orWhereIn('users.id', ServiceCase::whereNotNull('advisor_id')->select('user_id')))->count();
        $total = (clone $new)->count();
        $withApp = (clone $new)->whereIn('users.id', ApplicationPlan::query()->select('user_id'))->count();
        $viaAgent = (clone $new)->whereNotNull('managed_by_organisation_id')->count();

        return ['title' => 'Students who registered', 'headers' => ['Measure', 'Students'], 'rows' => [
            ['Registered', $total], ['Registered through an agent', $viaAgent], ['With a counsellor', $assigned], ['Without a counsellor', $total - $assigned], ['Started at least one application', $withApp],
        ]];
    }

    private function cases($from, $to): array
    {
        $opened = ServiceCase::whereBetween('opened_at', [$from, $to])->get();
        $rows = collect(ServiceCase::STATUSES)->map(fn ($label, $status) => [$label, $opened->where('status', $status)->count()])->values()->all();
        $rows[] = ['Opened in total', $opened->count()];
        $rows[] = ['Accepted in the period (any case)', ServiceCase::where('status', 'accepted')->whereBetween('outcome_at', [$from, $to])->count()];
        $rows[] = ['Rejected in the period (any case)', ServiceCase::where('status', 'rejected')->whereBetween('outcome_at', [$from, $to])->count()];

        return ['title' => 'Cases opened in the period, by their status now', 'headers' => ['Status', 'Cases'], 'rows' => $rows];
    }

    private function scholarships($from, $to): array
    {
        $plans = ApplicationPlan::with('cycle.programme')->where(fn ($q) => $q->whereBetween('created_at', [$from, $to])->orWhereBetween('review_requested_at', [$from, $to]))->get();
        $rows = $plans->groupBy(fn ($p) => $p->cycle?->programme?->title ?? 'Unknown')->map(fn (Collection $g, $title) => [
            $title, $g->filter(fn ($p) => $p->created_at->between($from, $to))->count(), $g->filter(fn ($p) => $p->review_requested_at?->between($from, $to))->count(), $g->where('stage', 'passed')->count(),
        ])->sortByDesc(fn ($r) => $r[1])->take(30)->values()->all();

        return ['title' => 'Applications by scholarship (top 30)', 'headers' => ['Scholarship', 'Started', 'Sent for review', 'Passed review (all time)'], 'rows' => $rows];
    }

    private function countries($from, $to): array
    {
        $plans = ApplicationPlan::with('cycle.destinations')->whereBetween('created_at', [$from, $to])->get();
        $counts = [];
        foreach ($plans as $p) {
            foreach (($p->cycle?->destinations ?? collect())->pluck('name')->whenEmpty(fn ($c) => $c->push('Not listed')) as $name) {
                $counts[$name]['started'] = ($counts[$name]['started'] ?? 0) + 1;
                $counts[$name]['sent'] = ($counts[$name]['sent'] ?? 0) + ($p->review_requested_at ? 1 : 0);
            }
        }
        arsort($counts);
        uasort($counts, fn ($a, $b) => $b['started'] <=> $a['started']);

        return ['title' => 'Applications started by destination country', 'headers' => ['Country', 'Started', 'Of those sent for review'], 'rows' => collect($counts)->map(fn ($c, $name) => [$name, $c['started'], $c['sent']])->values()->all()];
    }

    private function money($from, $to): array
    {
        $kinds = Invoice::KINDS;
        $rows = [];
        foreach ($kinds as $key => $label) {
            $rows[] = [$label.': issued', Invoice::money((int) Invoice::where('kind', $key)->whereBetween('issued_at', [$from, $to])->sum('amount_cents'))];
            $rows[] = [$label.': paid', Invoice::money((int) Invoice::where('kind', $key)->whereBetween('paid_at', [$from, $to])->sum('amount_cents'))];
        }
        $rows[] = ['Awaiting payment now (all)', Invoice::money((int) Invoice::where('status', 'issued')->sum('amount_cents'))];
        $rows[] = ['Agency charges unpaid now', Invoice::money((int) Invoice::where('kind', 'agency_charge')->where('status', 'issued')->sum('amount_cents'))];
        $rows[] = ['Refunds due now', Invoice::money((int) Invoice::where('refund_status', 'due')->sum('refund_cents'))];
        $rows[] = ['Refunds paid in the period', Invoice::money((int) Invoice::where('refund_status', 'paid')->whereBetween('refunded_at', [$from, $to])->sum('refund_cents'))];

        return ['title' => 'Fees and payments', 'headers' => ['Measure', 'Amount'], 'rows' => $rows];
    }

    private function agents($from, $to): array
    {
        $rows = PartnerOrganisation::where('kind', 'agent')->orderBy('name')->get()->map(function (PartnerOrganisation $org) use ($from, $to) {
            $students = $org->students()->pluck('users.id');

            return [$org->name, $org->students()->whereBetween('users.created_at', [$from, $to])->count(), ApplicationPlan::whereIn('user_id', $students)->whereBetween('created_at', [$from, $to])->count(), ApplicationPlan::whereIn('user_id', $students)->whereBetween('review_requested_at', [$from, $to])->count()];
        })->all();

        return ['title' => 'Agents', 'headers' => ['Agency', 'Students added', 'Applications started', 'Applications sent for review'], 'rows' => $rows];
    }
}
