<x-layouts.partner title="Organisation">
    <h1 class="text-2xl font-extrabold">Your organisation</h1>
    <dl class="card mt-4 grid gap-4 p-5 text-sm sm:grid-cols-3">
        <div><dt class="font-semibold">Name</dt><dd>{{ $organisation->name }}</dd></div>
        <div><dt class="font-semibold">Type</dt><dd>{{ \App\Models\PartnerOrganisation::KINDS[$organisation->kind] ?? $organisation->kind }}</dd></div>
        <div><dt class="font-semibold">Country</dt><dd>{{ $organisation->country?->name ?? '—' }}</dd></div>
    </dl>
    <p class="mt-2 text-xs text-muted">To change the name or type, write to <a class="underline" href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a>.</p>
    <form method="POST" action="{{ route('partner.organisation.update') }}" class="card mt-5 grid max-w-2xl gap-4 p-5">
        @csrf @method('PUT')
        <div><label class="label" for="contact_email">Contact email</label><input id="contact_email" name="contact_email" type="email" class="input" maxlength="190" value="{{ old('contact_email', $organisation->contact_email) }}">@error('contact_email')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="website">Website</label><input id="website" name="website" type="url" class="input" maxlength="300" placeholder="https://" value="{{ old('website', $organisation->website) }}">@error('website')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><button class="btn-primary rounded-lg">Save</button></div>
    </form>
</x-layouts.partner>