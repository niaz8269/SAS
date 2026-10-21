<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\TwoFactor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $user = Auth::user();
        if (! $user->isActive()) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'This account is not active. Please contact support.']);
        }

        // Anyone with an authenticator app (students who opted in, and all staff) must pass the challenge before a session exists.
        if (app(TwoFactor::class)->enabled($user)) {
            Auth::guard('web')->logout();
            $request->session()->regenerate();
            $request->session()->put('mfa', ['user_id' => $user->id, 'remember' => $request->boolean('remember'), 'at' => now()->timestamp]);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        return $user->isStaff()
            ? redirect()->intended('/admin')
            : redirect()->intended(route('student.dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'You have been logged out.');
    }
}
