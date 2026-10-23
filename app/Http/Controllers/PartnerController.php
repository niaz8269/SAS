<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Partner\Concerns\ResolvesOrganisation;
use App\Models\ApplicationPlan;
use Illuminate\Http\Request;

/** The recruiting agent's backend home: how many students and applications, and where each application stands. */
class PartnerController extends Controller
{
    use ResolvesOrganisation;

    public function __invoke(Request $request)
    {
        $organisation = $this->organisation($request);
        abort_unless($request->user()->isAgent(), 403);
        $plans = ApplicationPlan::whereHas('user', fn ($u) => $u->where('managed_by_organisation_id', $organisation->id));

        return view('partner.agent.home', [
            'organisation' => $organisation, 'students' => $organisation->students()->count(), 'applications' => (clone $plans)->count(),
            'byStage' => (clone $plans)->selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage'),
        ]);
    }
}
