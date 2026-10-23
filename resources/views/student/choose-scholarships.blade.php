<x-layouts.student title="Choose scholarships">
    <p class="text-sm font-semibold uppercase tracking-wide text-navy-700">Step 2 of 3</p>
    <h1 class="text-2xl font-extrabold">Choose scholarships</h1>
    <p class="mt-1 text-sm text-muted">Press <strong>Apply</strong> next to each scholarship you want. You can choose several. After that you will see the documents each one needs.</p>

    @if ($planned)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-navy-300 bg-navy-50 p-4">
            <p class="text-sm"><strong>{{ count($planned) }}</strong> {{ \Illuminate\Support\Str::plural('scholarship', count($planned)) }} chosen.</p>
            <a href="{{ route('student.applications.index') }}" class="btn-primary !py-1.5">Next: upload documents</a>
        </div>
    @endif

    <form method="GET" action="{{ route('student.choose.index') }}" class="mt-5 flex flex-wrap items-end gap-3" role="search">
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
                <a class="btn-outline" href="{{ route('student.applications.show', $planned[$c->id]) }}"><span aria-hidden="true">✓</span> Chosen. See documents</a>
            @else
                <form method="POST" action="{{ route('student.applications.store') }}">@csrf<input type="hidden" name="cycle_id" value="{{ $c->id }}"><button class="btn-primary" aria-label="Apply for {{ $c->programme->title }}">Apply</button></form>
            @endif
        </div>
    @empty
        <p class="card mt-3 p-6 text-sm text-muted">No scholarships match. Try another name or degree.</p>
    @endforelse
</x-layouts.student>
