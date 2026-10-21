<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Communication\Preferences;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'timezone' => ['nullable', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            'accept_privacy' => ['accepted'],
        ], ['accept_privacy.accepted' => 'Please confirm you have read the privacy notice.']);

        // Only whitelisted attributes are mass-assigned; role and status are never taken from the request (AUTH-05).
        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
            'timezone' => $data['timezone'] ?? 'UTC',
        ]);
        Preferences::ensureDefaults($user);

        event(new Registered($user)); // sends the verification email
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}