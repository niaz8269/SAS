<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\EmailCodes;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice(Request $request, EmailCodes $codes)
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect($user->homeUrl());
        }
        $codes->hasLiveCode($user) || $codes->issue($user); // a person who signs in without a live code (an agent added by staff, a lapsed code) gets one now

        return view('auth.verify-email');
    }

    /** The code typed back from the email. */
    public function confirm(Request $request, EmailCodes $codes)
    {
        $request->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => 'Enter the 6-digit code from the email.']);
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect($user->homeUrl());
        }
        if (! $codes->verify($user, $request->string('code')->toString())) {
            return back()->withErrors(['code' => 'That code is wrong, has expired or was already used. Check the newest email, or ask for a new code.']);
        }

        return $this->welcome($user);
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return $this->welcome($request->user());
    }

    public function send(Request $request, EmailCodes $codes)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect($request->user()->homeUrl());
        }

        return $codes->issue($request->user())
            ? back()->with('status', 'A new code has been sent. Older codes no longer work.')
            : back()->with('status', 'A code was sent a moment ago. Please wait a minute before asking for another.');
    }

    private function welcome(User $user)
    {
        $brand = config('sas.brand.short');
        if ($user->hasRole(Role::PartnerManager)) {
            return redirect()->route('partner.home')->with('status', 'Email verified. Welcome to '.$brand.'.');
        }

        // A new student starts by filling in their application form once; every scholarship they choose uses it.
        return redirect()->route('student.form.show')->with('status', 'Email verified. Welcome to '.$brand.'. Start by filling in your application form: you only do this once.');
    }
}
