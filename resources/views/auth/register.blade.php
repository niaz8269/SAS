<x-layouts.auth title="Create your account">
    <form method="POST" action="{{ route('register.store') }}" class="space-y-4" novalidate x-data="{ tz: Intl.DateTimeFormat().resolvedOptions().timeZone }">
        @csrf
        <input type="hidden" name="timezone" :value="tz">
        <div>
            <label for="name" class="label">Full name</label>
            <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name" class="input @error('name') input-error @enderror">
            @error('name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="input @error('email') input-error @enderror">
            @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="label">Password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="input @error('password') input-error @enderror" aria-describedby="pw-hint">
            <p id="pw-hint" class="field-hint">At least 10 characters, with letters and numbers.</p>
            @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="label">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
        </div>
        <div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="accept_privacy" value="1" class="mt-1 rounded border-slate-300 text-navy-700" @checked(old('accept_privacy'))>
                <span>I have read the <a class="text-navy-700 underline" href="{{ route('legal.show', 'privacy') }}" target="_blank" rel="noopener">privacy notice</a>.</span></label>
            @error('accept_privacy')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <p class="field-hint">Browsing scholarships never needs an account. An account lets you save scholarships, plan applications and keep documents privately.</p>
        <button class="btn-primary w-full">Create account</button>
        <p class="text-center text-sm text-muted">Already registered? <a class="text-navy-700 underline" href="{{ route('login') }}">Log in</a></p>
    </form>
</x-layouts.auth>