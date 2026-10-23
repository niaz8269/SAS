<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice(Request $request)
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('student.dashboard')
            : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        // A new student starts by filling in their application form once; every scholarship they choose uses it.
        return redirect()->route('student.form.show')->with('status', 'Email verified. Welcome to '.config('sas.brand.short').'. Start by filling in your application form: you only do this once.');
    }

    public function send(Request $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'A new verification link has been sent.');
    }
}
