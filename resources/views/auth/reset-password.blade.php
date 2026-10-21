<x-layouts.auth title="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="input @error('email') input-error @enderror">
            @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="label">New password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="input @error('password') input-error @enderror">
            @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="label">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
        </div>
        <button class="btn-primary w-full">Reset password</button>
    </form>
</x-layouts.auth>