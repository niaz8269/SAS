<x-layouts.partner title="Change password" crumb="Change password">
    <x-agent.title>Change password</x-agent.title>
    <form method="POST" action="{{ route('partner.password.update') }}" class="mt-6 max-w-3xl space-y-4" novalidate>
        @csrf @method('PUT')
        @foreach ([['current_password', 'Current password', 'current-password'], ['password', 'New password', 'new-password'], ['password_confirmation', 'Repeat the new password', 'new-password']] as [$name, $label, $auto])
            <div class="grid items-start gap-2 sm:grid-cols-[13.125rem_1fr] sm:gap-4"><label for="{{ $name }}" class="pt-2 sm:text-right"><span class="text-[#d9534f]" aria-hidden="true">*</span> {{ $label }}:</label>
                <div><input id="{{ $name }}" name="{{ $name }}" type="password" autocomplete="{{ $auto }}" required class="w-full max-w-lg border border-[#ccc] px-3 py-2 @error($name) border-[#d9534f] @enderror">@error($name)<p class="mt-1 text-sm text-[#d9534f]" role="alert">{{ $message }}</p>@enderror</div></div>
        @endforeach
        <p class="text-sm text-[#666] sm:pl-[14.125rem]">At least 8 characters, with letters and numbers. Other devices are signed out when you change it.</p>
        <div class="pt-2 sm:pl-[14.125rem]"><button class="inline-flex items-center gap-2 bg-[#6aaad8] px-7 py-3 text-[0.875rem] text-white hover:bg-[#5b9bc9]"><span aria-hidden="true">✔</span> submit</button></div>
    </form>
</x-layouts.partner>
