<x-layouts.public title="Book a consultation" description="Request a one-on-one consultation. Our team confirms a time with you.">
    <div class="container-page max-w-3xl py-10">
        <h1 class="text-3xl font-extrabold sm:text-4xl">Request a consultation</h1>
        <p class="mt-2 text-muted">Tell us when you are available. <strong>This is a request, not a confirmed booking</strong>: a member of the team will confirm a specific time with you. Advice does not guarantee scholarship selection, admission or visa approval.</p>

        <form method="POST" action="{{ route('consultations.store') }}" class="card mt-6 space-y-4 p-6" novalidate
              x-data="{ method: '{{ old('contact_method', 'email') }}', tz: '{{ old('student_timezone', auth()->user()?->timezone ?? '') }}' }" x-init="if (!tz || tz === 'UTC') tz = Intl.DateTimeFormat().resolvedOptions().timeZone">
            @csrf
            <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="name" class="label">Name</label><input id="name" name="name" required value="{{ old('name', auth()->user()?->name) }}" autocomplete="name" class="input @error('name') input-error @enderror">@error('name')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
                <div><label for="email" class="label">Email</label><input id="email" type="email" name="email" required value="{{ old('email', auth()->user()?->email) }}" autocomplete="email" class="input @error('email') input-error @enderror">@error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            </div>
            @if ($services->isNotEmpty())
                <div><label for="service_id" class="label">Topic (optional)</label>
                    <select id="service_id" name="service_id" class="input"><option value="">General advice</option>@foreach($services as $s)<option value="{{ $s->id }}" @selected(old('service_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="preferred_from" class="label">Earliest date</label><input id="preferred_from" type="date" name="preferred_from" required min="{{ now()->toDateString() }}" value="{{ old('preferred_from') }}" class="input @error('preferred_from') input-error @enderror">@error('preferred_from')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
                <div><label for="preferred_to" class="label">Latest date (optional)</label><input id="preferred_to" type="date" name="preferred_to" value="{{ old('preferred_to') }}" class="input @error('preferred_to') input-error @enderror">@error('preferred_to')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            </div>
            <div>
                <label for="student_timezone" class="label">Your time zone</label>
                <select id="student_timezone" name="student_timezone" x-model="tz" class="input @error('student_timezone') input-error @enderror">
                    @foreach (\DateTimeZone::listIdentifiers() as $zone)<option value="{{ $zone }}">{{ $zone }}</option>@endforeach
                </select>
                @error('student_timezone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <fieldset><legend class="label">Contact method</legend>
                    <label class="mr-4 inline-flex items-center gap-2 text-sm"><input type="radio" name="contact_method" value="email" x-model="method" class="text-forest-700"> Email</label>
                    <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="contact_method" value="phone" x-model="method" class="text-forest-700"> Phone call</label></fieldset>
                <div><label for="phone" class="label">Phone <span class="font-normal text-muted" x-text="method === 'phone' ? '(required)' : '(optional)'"></span></label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone', auth()->user()?->phone) }}" placeholder="+44 20 7946 0000" class="input @error('phone') input-error @enderror">@error('phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            </div>
            <div><label for="notes" class="label">Anything we should know? (optional)</label><textarea id="notes" name="notes" rows="4" class="input">{{ old('notes') }}</textarea></div>
            <div><label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-1 rounded border-slate-300 text-forest-700" @checked(old('consent'))><span>I agree that {{ config('sas.brand.short') }} may contact me to arrange this consultation.</span></label>@error('consent')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            <button class="btn-primary btn-lg">Request consultation</button>
        </form>
    </div>
</x-layouts.public>
