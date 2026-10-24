@php
    $place = collect([$university->city, $university->province])->filter()->unique()->implode(', ');
    $total = $levelCounts->sum();
@endphp
<x-layouts.public :title="$university->name" :description="\Illuminate\Support\Str::limit(strip_tags((string) $university->summary) ?: $university->name.($place ? ' in '.$place : '').': programmes, tuition and teaching languages for international students.', 155)">
    <section class="relative isolate overflow-hidden bg-navy-900">
        <img src="{{ $university->imageUrl() }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover object-center" width="960" height="540" fetchpriority="high">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-navy-900/90 via-navy-800/70 to-navy-900/55" aria-hidden="true"></div>
        <div class="container-page py-10 sm:py-14">
            <nav aria-label="Breadcrumb" class="text-sm text-white/80"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('universities.index') }}">Universities</a> / <span aria-current="page">{{ $university->name }}</span></nav>
            <div class="mt-5 grid items-center gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                <div class="text-white">
                    <h1 class="text-3xl font-extrabold text-white [text-shadow:0_0.125rem_1.25rem_rgba(10,34,80,0.5)] sm:text-4xl lg:text-5xl">{{ $university->name }}</h1>
                    @if ($university->name_zh)<p class="mt-1 text-lg text-white/80" lang="zh-Hans">{{ $university->name_zh }}</p>@endif
                    <dl class="mt-5 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                        <div><dt class="font-semibold text-gold-soft">Location</dt><dd>{{ $place ?: 'Not listed' }}</dd></div>
                        @if ($university->csc_key)<div><dt class="font-semibold text-gold-soft">Programmes listed</dt><dd>{{ $total ? number_format($total) : 'None listed on the official system' }}</dd></div>@endif
                        @if ($university->website)<div class="sm:col-span-2"><dt class="font-semibold text-gold-soft">Website</dt><dd><a class="underline" rel="noopener" href="{{ \App\Domain\Site\Site::href($university->website) }}">{{ $university->website }}</a></dd></div>@endif
                    </dl>
                    @if ($university->summary)<div class="mt-4 max-w-xl whitespace-pre-line text-white/90">{{ $university->summary }}</div>@endif
                </div>

                @if ($levelCounts->isNotEmpty())
                    <div class="rounded-2xl border border-white/30 bg-white/95 p-4 shadow-2xl shadow-navy-900/30 backdrop-blur sm:p-5">
                        <h2 class="text-lg font-bold">Find a programme</h2>
                        <ul class="mt-2 flex flex-wrap gap-2 text-sm" aria-label="Programmes by level">
                            @foreach (\App\Models\UniversityProgramme::LEVELS as $k => $v)
                                @if ($levelCounts->has($k))
                                    <li><a class="badge-neutral hover:underline" href="{{ route('universities.show', [$university, 'level' => $k]) }}">{{ $v }}: {{ number_format($levelCounts[$k]) }}</a></li>
                                @endif
                            @endforeach
                        </ul>
                        <form method="GET" action="{{ route('universities.show', $university) }}" class="mt-4 grid gap-3 sm:grid-cols-2" role="search">
                            <div class="sm:col-span-2"><label for="s-q" class="label">Subject or programme</label><input id="s-q" name="q" type="search" value="{{ $filters['q'] }}" class="input" maxlength="80"></div>
                            <div><label for="s-level" class="label">Degree level</label>
                                <select id="s-level" name="level" class="input"><option value="">Any level</option>@foreach (\App\Models\UniversityProgramme::LEVELS as $k => $v)<option value="{{ $k }}" @selected($filters['level'] === $k)>{{ $v }}</option>@endforeach</select></div>
                            <div><label for="s-lang" class="label">Teaching language</label>
                                <select id="s-lang" name="language" class="input"><option value="">Any language</option>@foreach ($languages as $l)<option value="{{ $l }}" @selected($filters['language'] === $l)>{{ $l }}</option>@endforeach</select></div>
                            <label class="flex items-start gap-2 text-sm sm:col-span-2"><input type="checkbox" name="csc" value="1" class="mt-0.5 rounded border-slate-300 text-navy-700" @checked($filters['csc'])> Only programmes listed as open to Chinese Government Scholarship applicants (&#9733;)</label>
                            <button class="btn-primary sm:col-span-2">Filter</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($feeStructures->isNotEmpty())
        @include('public.universities._medicine', ['place' => $place])
    @endif

    <section class="container-page py-8">
        <h2 class="section-title">Programmes</h2>
        @if ($levelCounts->isNotEmpty())
            <p class="mt-4 text-sm text-muted" aria-live="polite">{{ number_format($programmes->total()) }} {{ \Illuminate\Support\Str::plural('programme', $programmes->total()) }}</p>
            @if ($programmes->isEmpty())
                <p class="card mt-4 p-6 text-sm text-muted">No programmes match these filters.</p>
            @else
                @include('public.programmes._table', ['programmes' => $programmes, 'showUniversity' => false])
                <div class="mt-6">{{ $programmes->links('pagination::tailwind') }}</div>
            @endif
        @else
            <p class="card mt-4 p-6 text-sm text-muted">{{ $university->csc_key ? 'The official system lists no degree programmes for this university yet.' : 'This university is not on the official study-in-China programme list, so only its medicine programme is shown above.' }}</p>
        @endif
    </section>
</x-layouts.public>