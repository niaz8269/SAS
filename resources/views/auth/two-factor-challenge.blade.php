<x-layouts.auth title="Two-step verification">
    <form method="POST" action="{{ route('two-factor.store') }}" class="space-y-4" novalidate>
        @csrf
        <p class="text-sm text-muted">Enter the 6-digit code from your authenticator app, or one of your recovery codes.</p>
        <div>
            <label for="code" class="label">Code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus class="input @error('code') input-error @enderror">
            @error('code')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary w-full">Verify</button>
        <p class="text-center text-sm"><a class="text-forest-700 underline" href="{{ route('login') }}">Start again</a></p>
    </form>
</x-layouts.auth>
