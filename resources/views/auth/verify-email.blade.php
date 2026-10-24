<x-layouts.auth title="Verify your email">
    <p class="text-sm text-muted">We sent a 6-digit code to <strong class="text-ink">{{ auth()->user()->email }}</strong>. Type it below to confirm the address is yours. The code works for {{ \App\Domain\Identity\EmailCodes::TTL_MINUTES }} minutes. If you cannot find the email, look in your spam folder.</p>
    <form method="POST" action="{{ route('verification.confirm') }}" class="mt-5 space-y-4" novalidate>
        @csrf
        <div>
            <label for="code" class="label">Verification code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required autofocus placeholder="123456" class="input tracking-widest @error('code') input-error @enderror">
            @error('code')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary w-full">Verify my email</button>
    </form>
    <form method="POST" action="{{ route('verification.send') }}" class="mt-3">@csrf<button class="btn-ghost w-full">Send me a new code</button></form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="btn-ghost w-full">Log out</button></form>
</x-layouts.auth>
