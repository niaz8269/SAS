<x-layouts.partner title="Edit data" crumb="Edit data">
    <x-agent.title>Edit data</x-agent.title>
    <dl class="mt-6 grid max-w-3xl gap-3 text-[0.8125rem] sm:grid-cols-[13.125rem_1fr] sm:gap-x-4">
        <dt class="text-[#666] sm:text-right">Agency name:</dt><dd>{{ $organisation->name }}</dd>
        <dt class="text-[#666] sm:text-right">Country:</dt><dd>{{ $organisation->country?->name ?? '-' }}</dd>
    </dl>
    <p class="mt-2 max-w-3xl text-sm text-[#666] sm:pl-[14.125rem]">To change the name, write to <a class="text-[#428bb6] hover:underline" href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a>.</p>
    <form method="POST" action="{{ route('partner.organisation.update') }}" class="mt-6 max-w-3xl space-y-4" novalidate>
        @csrf @method('PUT')
        <div class="grid items-start gap-2 sm:grid-cols-[13.125rem_1fr] sm:gap-4"><label for="contact_email" class="pt-2 sm:text-right">Contact email:</label>
            <div><input id="contact_email" name="contact_email" type="email" maxlength="190" value="{{ old('contact_email', $organisation->contact_email) }}" class="w-full max-w-lg border border-[#ccc] px-3 py-2">@error('contact_email')<p class="mt-1 text-sm text-[#d9534f]" role="alert">{{ $message }}</p>@enderror</div></div>
        <div class="grid items-start gap-2 sm:grid-cols-[13.125rem_1fr] sm:gap-4"><label for="website" class="pt-2 sm:text-right">Website:</label>
            <div><input id="website" name="website" type="url" maxlength="300" placeholder="https://" value="{{ old('website', $organisation->website) }}" class="w-full max-w-lg border border-[#ccc] px-3 py-2">@error('website')<p class="mt-1 text-sm text-[#d9534f]" role="alert">{{ $message }}</p>@enderror</div></div>
        <div class="pt-2 sm:pl-[14.125rem]"><button class="inline-flex items-center gap-2 bg-[#6aaad8] px-7 py-3 text-[0.875rem] text-white hover:bg-[#5b9bc9]"><span aria-hidden="true">✔</span> submit</button></div>
    </form>
</x-layouts.partner>
