<x-dynamic-component :component="$ctx->layout()" title="Choose scholarships">
    <h1 class="text-2xl font-extrabold">{{ $ctx->isAgent() ? 'Add an application for '.$student->name : 'Choose scholarships' }}</h1>
    <p class="mt-1 text-sm text-muted">Press <strong>Apply</strong> next to a scholarship to start an application. You then follow four steps: apply, application form, upload materials, submit. You can start more than one.</p>

    @if ($planned)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-navy-300 bg-navy-50 p-4">
            <p class="text-sm"><strong>{{ count($planned) }}</strong> {{ \Illuminate\Support\Str::plural('application', count($planned)) }} started.</p>
            <a href="{{ $ctx->route('applications.index') }}" class="btn-primary !py-1.5">Go to the applications</a>
        </div>
    @endif

    <form method="GET" action="{{ $ctx->route('choose.index') }}" class="mt-5 flex flex-wrap items-end gap-3" role="search">
        <div class="min-w-[12rem] flex-1"><label for="q" class="label">Search by name</label><input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="80" class="input" placeholder="For example Chevening"></div>
        <div><label for="level" class="label">Degree</label>
            <select id="level" name="level" class="input"><option value="">All degrees</option>@foreach ($levels as $l)<option value="{{ $l->code }}" @selected($filters['level'] === $l->code)>{{ $l->name }}</option>@endforeach</select></div>
        <button class="btn-outline">Search</button>
        <label class="flex w-full items-center gap-2 text-sm"><input type="checkbox" name="show_closed" value="1" class="rounded border-slate-300 text-navy-700" @checked($filters['show_closed'])> Also show scholarships that are closed now</label>
    </form>

    <p class="mt-4 text-sm text-muted" aria-live="polite">{{ $cycles->count() }} {{ \Illuminate\Support\Str::plural('scholarship', $cycles->count()) }} shown</p>
    @forelse ($cycles as $c)
        @php($next = app(\App\Domain\Scholarship\DeadlineService::class)->nextClosing($c))
        <div class="card mt-3 flex flex-wrap items-center justify-between gap-3 p-4">
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ $c->programme->title }}</p>
                <p class="mt-0.5 text-sm text-muted">{{ $c->destinations->pluck('name')->implode(', ') ?: 'Country not listed' }} &middot; {{ $c->degreeLevels->pluck('name')->implode(', ') ?: 'Degree not listed' }}</p>
                <p class="mt-0.5 text-sm">@if ($next) Closes {{ $next->format('j M Y') }} @else <span class="text-muted">{{ $c->effectiveStatus()->label() }}</span> @endif
                    &middot; <a class="text-navy-700 underline" href="{{ route('scholarships.show', [$c->programme->slug, $c->slug]) }}">Read details</a></p>
            </div>
            @if (isset($planned[$c->id]))
                <a class="btn-outline" href="{{ $ctx->route('applications.show', $planned[$c->id]) }}"><span aria-hidden="true">✓</span> Open application</a>
            @else
                <form method="POST" action="{{ $ctx->route('applications.store') }}">@csrf<input type="hidden" name="cycle_id" value="{{ $c->id }}"><button class="btn-primary" aria-label="Apply for {{ $c->programme->title }}">Apply</button></form>
            @endif
        </div>
    @empty
        <p class="card mt-3 p-6 text-sm text-muted">No scholarships match. Try another name or degree.</p>
    @endforelse
</x-dynamic-component>
