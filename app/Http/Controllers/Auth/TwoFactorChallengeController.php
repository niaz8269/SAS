<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\TwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeController extends Controller
{
    private function pending(Request $request): ?User
    {
        $mfa = $request->session()->get('mfa');
        if (! $mfa || now()->timestamp - $mfa['at'] > 600) { // the password step is valid for 10 minutes
            $request->session()->forget('mfa');

            return null;
        }

        return User::find($mfa['user_id']);
    }

    public function create(Request $request)
    {
        return $this->pending($request) ? view('auth.two-factor-challenge') : redirect()->route('login')->with('error', 'Please log in again.');
    }

    public function store(Request $request, TwoFactor $twoFactor)
    {
        $user = $this->pending($request);
        if (! $user || ! $user->isActive()) {
            return redirect()->route('login')->with('error', 'Please log in again.');
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        if (! $twoFactor->challenge($user, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is not valid. Try the current code from your app or a recovery code.']);
        }

        $remember = $request->session()->get('mfa.remember', false);
        $request->session()->forget('mfa');
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended($user->homeUrl());
    }
}
