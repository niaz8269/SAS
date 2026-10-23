<?php

namespace App\Http\Controllers\Partner;

use App\Domain\Applications\ApplicationFormService;
use App\Http\Controllers\Controller;
use App\Models\ApplicationPlan;
use App\Models\ServiceCase;
use App\Models\User;
use Illuminate\Http\Request;

/** The applications of the students a recruiting agent looks after: status, review remarks, and fee status. */
class AgentApplicationController extends Controller
{
    public function index(Request $request, ApplicationFormService $forms)
    {
        $org = $request->user()->partnerOrganisation;
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'], 'stage' => ['nullable', 'in:draft,submitted,returned,passed'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $q = trim((string) ($filters['q'] ?? ''));
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100], true) ? (int) $request->query('per_page') : 25;
        $sort = in_array($request->query('sort'), ['id', 'application_no', 'name', 'updated_at'], true) ? $request->query('sort') : 'updated_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $plans = ApplicationPlan::whereHas('user', fn ($u) => $u->where('managed_by_organisation_id', $org->id))
            ->with(['user', 'cycle.programme'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('application_no', 'like', '%'.$q.'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'))
                ->orWhereHas('cycle.programme', fn ($p) => $p->where('title', 'like', '%'.$q.'%'))))
            ->when($filters['stage'] ?? null, fn ($query, $stage) => $query->where('stage', $stage))
            ->when($filters['from'] ?? null, fn ($query, $d) => $query->whereDate('updated_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($query, $d) => $query->whereDate('updated_at', '<=', $d))
            ->orderBy($sort === 'name' ? User::select('name')->whereColumn('users.id', 'application_plans.user_id') : $sort, $dir)->paginate($perPage)->withQueryString();

        // Fee status comes from the case SAS opened for the same student and scholarship, if any.
        $cases = ServiceCase::with('invoices')->whereIn('user_id', $plans->pluck('user_id'))->whereIn('cycle_id', $plans->pluck('cycle_id'))->get()
            ->groupBy(fn ($c) => $c->user_id.'-'.$c->cycle_id);

        return view('partner.agent.applications.index', ['plans' => $plans, 'filters' => ['q' => $q, 'stage' => $filters['stage'] ?? '', 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''], 'cases' => $cases, 'forms' => $forms, 'perPage' => $perPage, 'sort' => $sort, 'dir' => $dir]);
    }
}
