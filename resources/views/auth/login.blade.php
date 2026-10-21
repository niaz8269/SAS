<x-layouts.auth title="Log in">
    <form method="POST" action="{{ route('login.store') }}" class="space-y-4" novalidate>
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" autofocus class="input @error('email') input-error @enderror" aria-describedby="email-error">
            @error('email')<p id="email-error" class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="label">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="input @error('password') input-error @enderror">
            @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-center justify-between text-sm">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="remember" class="rounded border-slate-300 text-forest-700"> Remember me</label>
            <a class="text-forest-700 underline" href="{{ route('password.request') }}">Forgot password?</a>
        </div>
        <button class="btn-primary w-full">Log in</button>
        <p class="text-center text-sm text-muted">New here? <a class="text-forest-700 underline" href="{{ route('register') }}">Create a student account</a></p>
    </form>
</x-layouts.auth>