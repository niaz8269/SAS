<x-layouts.student title="Choose scholarships">
    <h1 class="text-2xl font-extrabold">Choose scholarships</h1>
    <p class="mt-1 text-sm text-muted">Pick the scholarships you want to apply for. For each one you will see the documents it needs. Your personal answers are filled in once, on your <a class="text-navy-700 underline" href="{{ route('student.form.show') }}">application form</a>, and a document you upload once counts for every scholarship that asks for it.</p>

    <form method="GET" action="{{ route('student.choose.index') }}" class="card mt-5 grid gap-3 p-4 sm:grid-cols-[1fr_200px_auto] sm:items-end" role="search">
        <div><label for="q" class="label">Scholarship name</label><input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="80" class="input"></div>
        <div><label for="level" class="label">Degree level</label>
            <select id="level" name="level" class="input"><option value="">Any level</option>@foreach ($levels as $l)<option value="{{ $l->code }}" @selected($filters['level'] === $l->code)>{{ $l->name }}</option>@endforeach</select></div>
        <button class="btn-primary">Search</button>
        <label class="flex items-center gap-2 text-sm sm:col-span-3"><input type="checkbox" name="show_closed" value="1" class="rounded border-slate-300 text-navy-700" @checked($filters['show_closed'])> Include scholarships whose call has closed</label>
    </form>

    <p class="mt-4 text-sm text-muted" aria-live="polite">{{ $cycles->count() }} {{ \Illuminate\Support\Str::plural('scholarship', $cycles->count()) }}</p>
    @forelse ($cycles as $c)
        <div class="card mt-3 flex flex-wrap items-center justify-between gap-3 p-4">
            <div class="min-w-0 flex-1">
                <a class="font-semibold hover:underline" href="{{ route('scholarships.show', [$c->programme->slug, $c->slug]) }}">{{ $c->programme->title }}</a>
                <p class="text-xs text-muted">{{ $c->programme->funder?->name }}@if ($c->destinations->isNotEmpty()) &middot; {{ $c->destinations->pluck('name')->implode(', ') }}@endif @if ($c->degreeLevels->isNotEmpty()) &middot; {{ $c->degreeLevels->pluck('name')->implode(', ') }}@endif</p>
                <x-deadline :cycle="$c" class="mt-1" />
            </div>
            @if (isset($planned[$c->id]))
                <a class="btn-outline" href="{{ route('student.applications.show', $planned[$c->id]) }}">Chosen: see documents</a>
            @else
                <form method="POST" action="{{ route('student.applications.store') }}">@csrf<input type="hidden" name="cycle_id" value="{{ $c->id }}"><button class="btn-primary">Apply for this</button></form>
            @endif
        </div>
    @empty
        <p class="card mt-3 p-6 text-sm text-muted">No scholarships match. Try another name or level.</p>
    @endforelse
</x-layouts.student>
