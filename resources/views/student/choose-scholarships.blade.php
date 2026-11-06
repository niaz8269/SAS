<x-dynamic-component :component="$ctx->layout()" title="Choose scholarships">
    <h1 class="text-2xl font-extrabold">{{ $ctx->isAgent() ? 'Add an application for '.$student->name : 'Choose scholarships' }}</h1>
    <p class="mt-2 text-base text-muted">Press <strong>Apply</strong> next to a scholarship to start an application. Each application has four steps: degree and language, your details, documents, then Send to SAS. You can start more than one.</p>

    @if ($planned)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gold bg-gold/10 p-4 text-base text-ink">
            <p><strong>{{ count($planned) }}</strong> {{ \Illuminate\Support\Str::plural('application', count($planned)) }} started.</p>
            <a href="{{ $ctx->route('applications.index') }}" class="btn-primary">Go to the applications</a>
        </div>
    @endif

    @if ($recommended->isNotEmpty())
        <div class="card mt-5 overflow-hidden">
            <div class="bg-navy-700 px-4 py-3 text-base text-white">Recommended for {{ $ctx->isAgent() ? $student->name : 'you' }}, from {{ $ctx->isAgent() ? 'their' : 'your' }} application form and goals</div>
            <ul class="divide-y divide-line">
                @foreach ($recommended as $m)
                    @php($c = $m['cycle'])
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-base">
                        <div class="min-w-0 flex-1"><p class="text-lg font-semibold">{{ $c->programme->title }}</p><p class="text-muted">Fits: {{ implode(', ', $m['why']) }} &middot; <a class="text-navy-700 underline" href="{{ route('scholarships.show', [$c->programme->slug, $c->slug]) }}">Read details</a></p></div>
                        @if (isset($planned[$c->id]))
                            <a class="btn-outline" href="{{ $ctx->route('applications.show', $planned[$c->id]) }}">✓ Open application</a>
                        @else
                            <form method="POST" action="{{ $ctx->route('applications.store') }}">@csrf<input type="hidden" name="cycle_id" value="{{ $c->id }}"><button class="btn-primary" aria-label="Apply for {{ $c->programme->title }}">Apply</button></form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ $ctx->route('choose.index') }}" class="card mt-5 flex flex-wrap items-end gap-3 p-4 text-base" role="search">
        <div class="min-w-[12rem] flex-1"><label for="q" class="mb-1 block text-sm font-medium">Search by name</label><input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="80" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2" placeholder="For example Chevening"></div>
        <div><label for="level" class="mb-1 block text-sm font-medium">Degree</label>
            <select id="level" name="level" class="rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="">All degrees</option>@foreach ($levels as $l)<option value="{{ $l->code }}" @selected($filters['level'] === $l->code)>{{ $l->name }}</option>@endforeach</select></div>
        <button class="btn-primary">Search</button>
        <label class="flex w-full items-center gap-2 text-sm"><input type="checkbox" name="show_closed" value="1" class="h-4 w-4 border-slate-300" @checked($filters['show_closed'])> Also show scholarships that are closed now</label>
    </form>

    <form id="many" method="POST" action="{{ $ctx->route('applications.store-many') }}" class="card mt-5 flex flex-wrap items-center justify-between gap-3 p-4 text-base">@csrf
        <p>Tick several scholarships in the list and start them all at once.</p>
        <button class="btn-outline">Start the ticked applications</button>
        @error('cycle_ids')<p class="w-full text-alert" role="alert">{{ $message }}</p>@enderror
    </form>
    <div class="card mt-3 overflow-hidden">
        <div class="bg-navy-700 px-4 py-3 text-base text-white" aria-live="polite">List &middot; {{ $cycles->count() }} {{ \Illuminate\Support\Str::plural('scholarship', $cycles->count()) }} shown</div>
        <ul class="divide-y divide-line">
            @forelse ($cycles as $c)
                @php($next = app(\App\Domain\Scholarship\DeadlineService::class)->nextClosing($c))
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    @unless (isset($planned[$c->id]))<input type="checkbox" form="many" name="cycle_ids[]" value="{{ $c->id }}" class="h-5 w-5" aria-label="Select {{ $c->programme->title }}">@else<span class="inline-block w-5"></span>@endunless
                    <div class="min-w-0 flex-1 text-base">
                        <p class="text-lg font-semibold text-ink">{{ $c->programme->title }}</p>
                        <p class="mt-0.5 text-muted">{{ $c->destinations->pluck('name')->implode(', ') ?: 'Country not listed' }} &middot; {{ $c->degreeLevels->pluck('name')->implode(', ') ?: 'Degree not listed' }}</p>
                        <p class="mt-0.5">@if ($next) Closes {{ $next->format('j M Y') }} @else <span class="text-muted">{{ $c->effectiveStatus()->label() }}</span> @endif
                            &middot; <a class="text-navy-700 underline" href="{{ route('scholarships.show', [$c->programme->slug, $c->slug]) }}">Read details</a></p>
                    </div>
                    @if (isset($planned[$c->id]))
                        <a class="btn-outline" href="{{ $ctx->route('applications.show', $planned[$c->id]) }}"><span aria-hidden="true">✓</span> Open application</a>
                    @else
                        <form method="POST" action="{{ $ctx->route('applications.store') }}">@csrf<input type="hidden" name="cycle_id" value="{{ $c->id }}"><button class="btn-primary" aria-label="Apply for {{ $c->programme->title }}">Apply</button></form>
                    @endif
                </li>
            @empty
                <li class="px-4 py-10 text-center text-base text-muted">No scholarships match. Try another name or degree.</li>
            @endforelse
        </ul>
    </div>
</x-dynamic-component>
