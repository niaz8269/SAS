<?php

namespace App\Http\Controllers\Partner;

use App\Domain\Editorial\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Partner\Concerns\ResolvesOrganisation;
use Illuminate\Http\Request;

class OrganisationController extends Controller
{
    use ResolvesOrganisation;

    public function edit(Request $request)
    {
        $organisation = $this->organisation($request);
        $this->authorize('update', $organisation);

        return view('partner.organisation', ['organisation' => $organisation]);
    }

    public function update(Request $request)
    {
        $organisation = $this->organisation($request);
        $this->authorize('update', $organisation);
        $data = $request->validate([
            'contact_email' => ['nullable', 'email', 'max:190'],
            'website' => ['nullable', 'string', 'max:300', 'regex:~^https?://[^\s]+$~i'],
        ], ['website.regex' => 'Give the full address, starting with https://.']);
        $before = $organisation->only(['contact_email', 'website']);
        $organisation->fill($data)->save(); // name, type and status are changed by SAS staff only
        AuditLog::record('partner.organisation_updated', $organisation, $before, $data);

        return redirect()->route('partner.organisation.edit')->with('status', 'Saved.');
    }
}
