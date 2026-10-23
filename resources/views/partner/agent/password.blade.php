<x-layouts.partner title="Change password">
    <h1 class="text-2xl font-extrabold">Change password</h1>
    <form method="POST" action="{{ route('partner.password.update') }}" class="card mt-5 max-w-md space-y-4 p-5" novalidate>
        @csrf @method('PUT')
        @foreach ([['current_password', 'Current password', 'current-password'], ['password', 'New password (at least 10 characters, with letters and numbers)', 'new-password'], ['password_confirmation', 'Repeat the new password', 'new-password']] as [$name, $label, $auto])
            <div><label for="{{ $name }}" class="label">{{ $label }}</label><input id="{{ $name }}" name="{{ $name }}" type="password" autocomplete="{{ $auto }}" required class="input @error($name) input-error @enderror">@error($name)<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
        @endforeach
        <button class="btn-primary">Change password</button>
        <p class="text-xs text-muted">Other devices are signed out when you change it.</p>
    </form>
</x-layouts.partner>
