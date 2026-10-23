@php
    $intro = \App\Models\PageBlock::builtIn('universities', 'intro');
    $emptyMsg = \App\Models\PageBlock::builtIn('universities', 'empty');
@endphp
<x-layouts.public title="Universities in China" :description="$intro?->subheading ?: 'Browse universities in China that accept international students, with their programmes, tuition and teaching languages.'">
    <x-page-blocks page="universities" position="top" />
    <section class="container-page py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <span aria-current="page">Universities</span></nav>
        <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">{{ $intro?->heading ?: 'Universities in China' }}</h1>
        <p class="mt-2 max-w-3xl text-muted">{{ $intro?->body ?: 'Universities listed on the official study-in-China system for international students. Programme lists, tuition and scholarship eligibility are copied from that system and change every year: always confirm with the university and the official scholarship pages.' }}</p>
        <p class="mt-2"><a class="font-semibold text-navy-700 underline" href="{{ route('programmes.index') }}">Search programmes across all universities &rarr;</a></p>

        <form method="GET" action="{{ route('universities.index') }}" class="card mt-6 grid gap-3 p-4 md:grid-cols-[2fr_1fr_1fr_auto] md:items-end" role="search">
            <div><label for="u-q" class="label">University name</label><input id="u-q" name="q" type="search" value="{{ $filters['q'] }}" class="input" placeholder="English or Chinese name" maxlength="80"></div>
            <div><label for="u-prov" class="label">Province</label>
                <select id="u-prov" name="province" class="input"><option value="">Any province</option>@foreach ($provinces as $p)<option value="{{ $p }}" @selected($filters['province'] === $p)>{{ $p }}</option>@endforeach</select></div>
            <div><label for="u-sort" class="label">Sort by</label>
                <select id="u-sort" name="sort" class="input"><option value="name" @selected($filters['sort'] === 'name')>Name</option><option value="programmes" @selected($filters['sort'] === 'programmes')>Most programmes</option></select></div>
            <button class="btn-primary">Search</button>
        </form>

        <p class="mt-4 text-sm text-muted" aria-live="polite">{{ number_format($universities->total()) }} {{ \Illuminate\Support\Str::plural('university', $universities->total()) }}</p>

        @if ($universities->isEmpty())
            <p class="card mt-4 p-6 text-sm text-muted">{{ $emptyMsg?->body ?: 'No universities match your search.' }}</p>
        @else
            <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($universities as $u)
                    <li class="card flex flex-col overflow-hidden">
                        <a href="{{ route('universities.show', $u) }}" tabindex="-1" aria-hidden="true"><img src="{{ $u->imageUrl() }}" alt="" class="aspect-video w-full object-cover" loading="lazy" width="960" height="540"></a>
                        <div class="flex flex-1 flex-col p-5">
                            <h2 class="text-lg font-bold"><a class="hover:underline" href="{{ route('universities.show', $u) }}">{{ $u->name }}</a></h2>
                            @if ($u->name_zh)<p class="text-sm text-muted" lang="zh-Hans">{{ $u->name_zh }}</p>@endif
                            <p class="mt-2 text-sm">{{ collect([$u->city, $u->province])->filter()->unique()->implode(', ') ?: 'Location not listed' }}</p>
                            <p class="mt-1 text-sm text-muted">{{ $u->programmes_count ? number_format($u->programmes_count).' programmes' : 'No programmes listed yet' }}</p>
                            <a class="btn-outline mt-4 self-start" href="{{ route('universities.show', $u) }}">View programmes</a>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $universities->links('pagination::tailwind') }}</div>
        @endif
    </section>
    <x-page-blocks page="universities" position="bottom" />
</x-layouts.public>