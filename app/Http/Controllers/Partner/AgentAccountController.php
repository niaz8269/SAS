<?php

namespace App\Http\Controllers\Partner;

use App\Domain\Editorial\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** A partner user changes their own password. */
class AgentAccountController extends Controller
{
    public function passwordForm()
    {
        return view('partner.agent.password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password']]);
        $user = $request->user();
        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete(); // sign out other devices
        AuditLog::record('partner.password_changed', $user, null, null, null, $user);

        return redirect()->route('partner.password.edit')->with('status', 'Your password was changed.');
    }
}
