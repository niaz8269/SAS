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
            <x-password-input id="password" name="password" autocomplete="new-password" :strength="true" :invalid="$errors->has('password')" />
            @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="label">Confirm new password</label>
            <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
        </div>
        <button class="btn-primary w-full">Reset password</button>
    </form>
</x-layouts.auth>