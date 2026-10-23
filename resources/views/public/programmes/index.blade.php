@php
    $intro = \App\Models\PageBlock::builtIn('programmes', 'intro');
    $emptyMsg = \App\Models\PageBlock::builtIn('programmes', 'empty');
@endphp
<x-layouts.public title="Search programmes" :description="$intro?->subheading ?: 'Search degree programmes at universities in China by subject, level, language, province and Chinese Government Scholarship eligibility.'">
    <x-page-blocks page="programmes" position="top" />
    <section class="container-page py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('universities.index') }}">Universities</a> / <span aria-current="page">Programmes</span></nav>
        <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">{{ $intro?->heading ?: 'Search programmes' }}</h1>
        <p class="mt-2 max-w-3xl text-muted">{{ $intro?->body ?: 'Find degree programmes at universities in China by subject, degree level, teaching language and province.' }}</p>

        <form method="GET" action="{{ route('programmes.index') }}" class="card mt-6 grid gap-3 p-4 md:grid-cols-4 md:items-end" role="search">
            <div class="md:col-span-2"><label for="p-q" class="label">Subject or programme</label><input id="p-q" name="q" type="search" value="{{ $filters['q'] }}" class="input" placeholder="e.g. computer science, 临床医学" maxlength="80"></div>
            <div><label for="p-level" class="label">Degree level</label>
                <select id="p-level" name="level" class="input"><option value="">Any level</option>@foreach (\App\Models\UniversityProgramme::LEVELS as $k => $v)<option value="{{ $k }}" @selected($filters['level'] === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label for="p-lang" class="label">Teaching language</label>
                <select id="p-lang" name="language" class="input"><option value="">Any language</option>@foreach ($languages as $l)<option value="{{ $l }}" @selected($filters['language'] === $l)>{{ $l }}</option>@endforeach</select></div>
            <div><label for="p-prov" class="label">Province</label>
                <select id="p-prov" name="province" class="input"><option value="">Any province</option>@foreach ($provinces as $p)<option value="{{ $p }}" @selected($filters['province'] === $p)>{{ $p }}</option>@endforeach</select></div>
            <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" name="csc" value="1" class="rounded border-slate-300 text-navy-700" @checked($filters['csc'])> Only programmes listed as open to Chinese Government Scholarship applicants (&#9733;)</label>
            <button class="btn-primary">Search programmes</button>
        </form>

        @include('public.programmes._notice')
        <p class="mt-4 text-sm text-muted" aria-live="polite">{{ number_format($programmes->total()) }} {{ \Illuminate\Support\Str::plural('programme', $programmes->total()) }}</p>

        @if ($programmes->isEmpty())
            <p class="card mt-4 p-6 text-sm text-muted">{{ $emptyMsg?->body ?: 'No programmes match your search. Try fewer filters or a broader word.' }}</p>
        @else
            @include('public.programmes._table', ['programmes' => $programmes, 'showUniversity' => true])
            <div class="mt-6">{{ $programmes->links('pagination::tailwind') }}</div>
        @endif
    </section>
    <x-page-blocks page="programmes" position="bottom" />
</x-layouts.public>