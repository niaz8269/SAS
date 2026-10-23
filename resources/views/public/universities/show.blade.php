@php
    $place = collect([$university->city, $university->province])->filter()->unique()->implode(', ');
    $total = $levelCounts->sum();
@endphp
<x-layouts.public :title="$university->name" :description="\Illuminate\Support\Str::limit(strip_tags((string) $university->summary) ?: $university->name.($place ? ' in '.$place : '').': programmes, tuition and teaching languages for international students.', 155)">
    <section class="container-page py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('universities.index') }}">Universities</a> / <span aria-current="page">{{ $university->name }}</span></nav>
        <div class="mt-3 grid gap-6 {{ $university->image_path ? 'lg:grid-cols-[1.2fr_0.8fr]' : '' }}">
            <div>
                <h1 class="text-3xl font-extrabold sm:text-4xl">{{ $university->name }}</h1>
                @if ($university->name_zh)<p class="mt-1 text-lg text-muted" lang="zh-Hans">{{ $university->name_zh }}</p>@endif
                <dl class="mt-4 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="font-semibold">Location</dt><dd>{{ $place ?: 'Not listed' }}</dd></div>
                    <div><dt class="font-semibold">Programmes listed</dt><dd>{{ $total ? number_format($total) : 'None listed on the official system' }}</dd></div>
                    @if ($university->website)<div><dt class="font-semibold">Website</dt><dd><a class="text-navy-700 underline" rel="noopener" href="{{ \App\Domain\Site\Site::href($university->website) }}">{{ $university->website }}</a></dd></div>@endif
                    @if ($university->source_url)<div><dt class="font-semibold">Official listing</dt><dd><a class="text-navy-700 underline" rel="noopener" href="{{ \App\Domain\Site\Site::href($university->source_url) }}">campuschina.org</a>@if ($university->catalogue_checked_at) <span class="text-muted">(read {{ $university->catalogue_checked_at->format('j M Y') }})</span>@endif</dd></div>@endif
                </dl>
                @if ($university->summary)<div class="mt-4 whitespace-pre-line">{{ $university->summary }}</div>@endif
            </div>
            @if ($university->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public_media')->url($university->image_path) }}" alt="" class="w-full rounded-xl object-cover" loading="lazy">@endif
        </div>

        @include('public.programmes._notice')

        <h2 class="section-title mt-8">Programmes</h2>
        @if ($levelCounts->isNotEmpty())
            <ul class="mt-2 flex flex-wrap gap-2 text-sm" aria-label="Programmes by level">
                @foreach (\App\Models\UniversityProgramme::LEVELS as $k => $v)
                    @if ($levelCounts->has($k))
                        <li><a class="badge-neutral hover:underline" href="{{ route('universities.show', [$university, 'level' => $k]) }}">{{ $v }}: {{ number_format($levelCounts[$k]) }}</a></li>
                    @endif
                @endforeach
            </ul>

            <form method="GET" action="{{ route('universities.show', $university) }}" class="card mt-4 grid gap-3 p-4 md:grid-cols-4 md:items-end" role="search">
                <div class="md:col-span-2"><label for="s-q" class="label">Subject or programme</label><input id="s-q" name="q" type="search" value="{{ $filters['q'] }}" class="input" maxlength="80"></div>
                <div><label for="s-level" class="label">Degree level</label>
                    <select id="s-level" name="level" class="input"><option value="">Any level</option>@foreach (\App\Models\UniversityProgramme::LEVELS as $k => $v)<option value="{{ $k }}" @selected($filters['level'] === $k)>{{ $v }}</option>@endforeach</select></div>
                <div><label for="s-lang" class="label">Teaching language</label>
                    <select id="s-lang" name="language" class="input"><option value="">Any language</option>@foreach ($languages as $l)<option value="{{ $l }}" @selected($filters['language'] === $l)>{{ $l }}</option>@endforeach</select></div>
                <label class="flex items-center gap-2 text-sm md:col-span-3"><input type="checkbox" name="csc" value="1" class="rounded border-slate-300 text-navy-700" @checked($filters['csc'])> Only programmes listed as open to Chinese Government Scholarship applicants (&#9733;)</label>
                <button class="btn-primary">Filter</button>
            </form>

            <p class="mt-4 text-sm text-muted" aria-live="polite">{{ number_format($programmes->total()) }} {{ \Illuminate\Support\Str::plural('programme', $programmes->total()) }}</p>
            @if ($programmes->isEmpty())
                <p class="card mt-4 p-6 text-sm text-muted">No programmes match these filters.</p>
            @else
                @include('public.programmes._table', ['programmes' => $programmes, 'showUniversity' => false])
                <div class="mt-6">{{ $programmes->links('pagination::tailwind') }}</div>
            @endif
        @else
            <p class="card mt-4 p-6 text-sm text-muted">The official system lists no degree programmes for this university yet.</p>
        @endif
    </section>
</x-layouts.public>