<x-layouts.public title="Report a correction" :noindex="true">
    <div class="container-page max-w-2xl py-10">
        <h1 class="text-3xl font-extrabold">Accuracy and corrections</h1>
        <p class="mt-2 text-muted">Spotted something out of date or wrong? Tell us. Our editors check the official source and update the listing. Reporting a problem moves that listing to the front of our review queue.</p>
        <form method="POST" action="{{ route('corrections.store') }}" class="card mt-6 space-y-4 p-6" novalidate>
            @csrf
            <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            @if ($cycle)
                <input type="hidden" name="cycle_id" value="{{ $cycle->id }}">
                <p class="rounded-md bg-navy-50 px-4 py-3 text-sm">About: <strong>{{ $cycle->label() }}</strong></p>
            @else
                <div><label for="page_url" class="label">Page address (optional)</label><input id="page_url" type="url" name="page_url" value="{{ old('page_url') }}" class="input @error('page_url') input-error @enderror">@error('page_url')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            @endif
            <div><label for="message" class="label">What should be corrected?</label><textarea id="message" name="message" rows="5" required class="input @error('message') input-error @enderror">{{ old('message') }}</textarea>@error('message')<p class="field-error" role="alert">{{ $message }}</p>@enderror<p class="field-hint">A link to the official page helps.</p></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="reporter_name" class="label">Your name (optional)</label><input id="reporter_name" name="reporter_name" value="{{ old('reporter_name') }}" class="input"></div>
                <div><label for="reporter_email" class="label">Your email (optional)</label><input id="reporter_email" type="email" name="reporter_email" value="{{ old('reporter_email') }}" class="input">@error('reporter_email')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            </div>
            <button class="btn-primary">Send report</button>
        </form>
    </div>
</x-layouts.public>
