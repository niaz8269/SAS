<?php

namespace App\Http\Controllers\Partner\Concerns;

use App\Models\PartnerOrganisation;
use Illuminate\Http\Request;

trait ResolvesOrganisation
{
    /** The signed-in partner's own, active organisation. Anyone else (students, other roles, suspended partners) gets 403. */
    protected function organisation(Request $request): PartnerOrganisation
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('partner_manager') && $user->partner_organisation_id, 403);
        $organisation = $user->partnerOrganisation;
        abort_unless($organisation && $organisation->isActive(), 403);

        return $organisation;
    }
}
