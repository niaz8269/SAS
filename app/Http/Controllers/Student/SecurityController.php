<?php

namespace App\Http\Controllers\Student;

use App\Domain\Editorial\AuditLog;
use App\Domain\Identity\TwoFactor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Optional multi-factor authentication for students (AUTH-02). */
class SecurityController extends Controller
{
    /** The Security page only exists while two-step sign-in is switched on (SAS_TWO_STEP). */
    private function available(): void
    {
        abort_unless(TwoFactor::available(), 404);
    }

    public function show(Request $request, TwoFactor $twoFactor)
    {
        $this->available();
        $user = $request->user();
        $secret = null;
        if (! $twoFactor->enabled($user)) {
            $secret = $request->session()->get('mfa_setup_secret') ?? tap($twoFactor->newSecret(), fn ($s) => $request->session()->put('mfa_setup_secret', $s));
        }

        return view('student.security', [
            'enabled' => $twoFactor->enabled($user), 'secret' => $secret,
            'qr' => $secret ? $twoFactor->qrCode($user, $secret) : null,
            'recoveryCodes' => $request->session()->pull('mfa_new_codes'),
        ]);
    }

    public function enable(Request $request, TwoFactor $twoFactor)
    {
        $this->available();
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $secret = $request->session()->get('mfa_setup_secret');
        if (! $secret || ! $twoFactor->verifyCode($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code did not match. Check the time on your phone and try again.']);
        }
        $codes = $twoFactor->enable($request->user(), $secret);
        $request->session()->forget('mfa_setup_secret');
        $request->session()->put('mfa_new_codes', $codes);
        AuditLog::record('mfa.enabled', $request->user(), null, null, null, $request->user());

        return redirect()->route('student.security')->with('status', 'Two-step verification is on. Save your recovery codes now.');
    }

    public function disable(Request $request, TwoFactor $twoFactor)
    {
        $this->available();
        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password is incorrect.']);
        }
        if ($request->user()->isStaff()) {
            return back()->with('error', 'Staff accounts must keep two-step verification on.');
        }
        $twoFactor->disable($request->user());
        AuditLog::record('mfa.disabled', $request->user(), null, null, null, $request->user());

        return back()->with('status', 'Two-step verification is off.');
    }

    public function regenerate(Request $request, TwoFactor $twoFactor)
    {
        $this->available();
        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password is incorrect.']);
        }
        $codes = $twoFactor->newRecoveryCodes();
        $request->user()->forceFill(['app_authentication_recovery_codes' => $codes])->save();
        $request->session()->put('mfa_new_codes', $codes);

        return redirect()->route('student.security')->with('status', 'New recovery codes created. The old ones no longer work.');
    }
}
