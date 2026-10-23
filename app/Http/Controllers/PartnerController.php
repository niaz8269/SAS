<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Partner\Concerns\ResolvesOrganisation;
use App\Models\ApplicationPlan;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    use ResolvesOrganisation;

    public function __invoke(Request $request)
    {
        $organisation = $this->organisation($request);

        if ($request->user()->isAgent()) {
            $plans = ApplicationPlan::whereHas('user', fn ($u) => $u->where('managed_by_organisation_id', $organisation->id));

            return view('partner.agent.home', [
                'organisation' => $organisation, 'students' => $organisation->students()->count(), 'applications' => (clone $plans)->count(),
                'byStage' => (clone $plans)->selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage'),
            ]);
        }

        $submissions = $organisation->submissions()->latest('id')->get();

        return view('partner.home', ['organisation' => $organisation, 'submissions' => $submissions, 'counts' => $submissions->countBy('status')]);
    }
}
