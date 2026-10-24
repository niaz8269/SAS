<?php

namespace App\Http\Controllers\Partner;

use App\Domain\Editorial\AuditLog;
use App\Http\Controllers\Controller;
use App\Models\ApplicationPlan;
use App\Models\Invoice;
use App\Models\ServiceCase;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** An agency's own lists as CSV, and its monthly statement of fees. Everything is limited to the agency's own students. */
class AgentExportController extends Controller
{
    public function students(Request $request)
    {
        $org = $request->user()->partnerOrganisation;
        $students = User::where('managed_by_organisation_id', $org->id)->with('applicationForm')->withCount('applicationPlans')->orderBy('name')->get();
        AuditLog::record('agent.export_students', $org, null, ['rows' => $students->count()], null, $request->user());

        return Csv::download('students-'.now()->toDateString().'.csv', ['Name', 'Email', 'Account', 'Country', 'Passport number', 'Date of birth', 'Applications', 'Added on'],
            $students->map(fn (User $s) => [$s->name, $s->email, $s->email_verified_at ? 'Active' : 'Invitation pending', $s->applicationForm?->value('nationality'), $s->applicationForm?->value('passport_no'), $s->applicationForm?->value('date_of_birth'), $s->application_plans_count, $s->created_at->toDateString()]));
    }

    public function applications(Request $request)
    {
        $org = $request->user()->partnerOrganisation;
        $plans = ApplicationPlan::whereHas('user', fn ($u) => $u->where('managed_by_organisation_id', $org->id))->with(['user', 'cycle.programme', 'degreeLevel'])->orderByDesc('updated_at')->get();
        $cases = ServiceCase::with('invoices')->whereIn('user_id', $plans->pluck('user_id'))->get()->groupBy(fn ($c) => $c->user_id.'-'.$c->cycle_id);
        AuditLog::record('agent.export_applications', $org, null, ['rows' => $plans->count()], null, $request->user());

        return Csv::download('applications-'.now()->toDateString().'.csv', ['Application number', 'Student', 'Scholarship', 'Degree', 'Status', 'Review notes', 'Application fee', 'Agency charge', 'Last change'],
            $plans->map(function (ApplicationPlan $p) use ($cases) {
                $case = ($cases[$p->user_id.'-'.$p->cycle_id] ?? collect())->first();
                $fee = fn ($kind) => $case?->invoices->where('kind', $kind)->where('status', '!=', 'void')->last()?->feeLabel() ?? '-';

                return [$p->application_no, $p->user->name, $p->cycle->programme->title, $p->degreeLevel?->name, $p->statusFor($case), $p->review_remarks, $fee('application_fee'), $fee('agency_charge'), $p->updated_at->toDateTimeString()];
            }));
    }

    /** The month's fees for the agency's students: what was issued, what was paid and what is still owed. */
    public function statement(Request $request)
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'format' => ['nullable', 'in:csv']]);
        $month = Carbon::createFromFormat('Y-m-d', ($data['month'] ?? now()->format('Y-m')).'-01')->startOfMonth();
        $org = $request->user()->partnerOrganisation;
        $students = User::where('managed_by_organisation_id', $org->id)->pluck('id');
        $invoices = Invoice::whereIn('user_id', $students)->where('status', '!=', 'void')->with(['serviceCase', 'student'])
            ->where(fn ($q) => $q->whereBetween('issued_at', [$month, $month->copy()->endOfMonth()])->orWhereBetween('paid_at', [$month, $month->copy()->endOfMonth()]))->orderBy('issued_at')->get();

        if (($data['format'] ?? null) === 'csv') {
            AuditLog::record('agent.export_statement', $org, null, ['month' => $month->format('Y-m'), 'rows' => $invoices->count()], null, $request->user());

            return Csv::download('statement-'.$month->format('Y-m').'.csv', ['Student', 'Case', 'Fee', 'Amount', 'Status', 'Issued', 'Paid'],
                $invoices->map(fn (Invoice $i) => [$i->student?->name, $i->serviceCase?->title, Invoice::KINDS[$i->kind] ?? $i->kind, $i->amount(), $i->feeLabel(), $i->issued_at?->toDateString(), $i->paid_at?->toDateString()]));
        }

        $issuedCents = $invoices->filter(fn ($i) => $i->issued_at?->between($month, $month->copy()->endOfMonth()))->sum('amount_cents');
        $paidCents = $invoices->filter(fn ($i) => $i->paid_at?->between($month, $month->copy()->endOfMonth()))->sum('amount_cents');
        $owedCents = Invoice::whereIn('user_id', $students)->where('status', 'issued')->sum('amount_cents');

        return view('partner.agent.statement', [
            'month' => $month, 'invoices' => $invoices, 'issued' => Invoice::money((int) $issuedCents), 'paid' => Invoice::money((int) $paidCents), 'owed' => Invoice::money((int) $owedCents),
            'previous' => $month->copy()->subMonth()->format('Y-m'), 'next' => $month->copy()->addMonth()->format('Y-m'), 'isCurrent' => $month->isSameMonth(now()),
        ]);
    }
}
