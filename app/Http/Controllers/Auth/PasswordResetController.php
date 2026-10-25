<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            report($e); // the mail service refused us; the answer below stays the same so nothing about accounts leaks
        }

        // Always the same answer so the form cannot be used to discover which emails have accounts.
        return back()->with('status', 'If that email has an account, a reset link is on its way.');
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $reset = function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $user->hasVerifiedEmail() || $user->markEmailAsVerified(); // the link was sent to this address, so using it proves the address is theirs
            DB::table('sessions')->where('user_id', $user->id)->delete(); // revoke other sessions
            event(new PasswordReset($user));
        };
        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');
        $status = Password::reset($credentials, $reset);
        if ($status === Password::InvalidToken) {
            $status = Password::broker('invites')->reset($credentials, $reset); // a student invited by an agent has a week, not an hour
        }

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('status', 'Your password has been reset. You can now log in.')
            : back()->withErrors(['email' => 'This reset link is invalid or has expired.']);
    }
}
