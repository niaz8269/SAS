<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Partner\Concerns\ResolvesOrganisation;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    use ResolvesOrganisation;

    public function __invoke(Request $request)
    {
        $organisation = $this->organisation($request);
        $submissions = $organisation->submissions()->latest('id')->get();

        return view('partner.home', ['organisation' => $organisation, 'submissions' => $submissions,
            'counts' => $submissions->countBy('status')]);
    }
}
