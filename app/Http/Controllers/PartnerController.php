<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->hasRole(Role::PartnerManager), 403);

        return view('partner.home');
    }
}
