<x-layouts.auth title="Reset your password">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4" novalidate>
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input @error('email') input-error @enderror">
            @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary w-full">Email me a reset link</button>
        <p class="text-center text-sm"><a class="text-forest-700 underline" href="{{ route('login') }}">Back to log in</a></p>
    </form>
</x-layouts.auth>