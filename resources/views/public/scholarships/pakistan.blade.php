@php
    $isHub = $page['facet'] === null;
    $crumbs = $isHub
        ? [['Home', route('home')], ['Scholarships', route('scholarships.index')], ['Scholarships for Pakistani students']]
        : [['Home', route('home')], ['Scholarships', route('scholarships.index')], ['Scholarships for Pakistani students', route('pakistan.hub')], [$page['label']]];
    $description = \Illuminate\Support\Str::limit($content['paragraphs'][0], 155);
@endphp
<x-layouts.public :title="$page['title']" :description="$description" :crumbs="$crumbs">
    <x-slot:head>
        <x-jsonld :data="['@type' => 'ItemList', 'name' => $page['title'], 'numberOfItems' => $page['count'], 'itemListElement' => $cycles->getCollection()->values()->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => ($cycles->currentPage() - 1) * $cycles->perPage() + $i + 1, 'url' => route('scholarships.show', [$c->programme->slug, $c->slug]), 'name' => $c->programme->title])->all()]" />
        <x-jsonld :data="['@type' => 'FAQPage', 'mainEntity' => collect($content['faq'])->map(fn ($qa) => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]])->all()]" />
    </x-slot:head>

    <section class="border-b border-line bg-white">
        <div class="container-page py-8">
            <nav aria-label="Breadcrumb" class="text-sm text-muted">
                <a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('scholarships.index') }}">Scholarships</a> /
                @if ($isHub)<span aria-current="page">Scholarships for Pakistani students</span>
                @else<a class="hover:underline" href="{{ route('pakistan.hub') }}">Scholarships for Pakistani students</a> / <span aria-current="page">{{ $page['label'] }}</span>@endif
            </nav>
            <h1 class="mt-3 text-3xl font-extrabold sm:text-4xl">{{ $page['title'] }}</h1>
            <p class="mt-3 max-w-3xl text-lg text-muted">{{ $content['paragraphs'][0] }}</p>
            @if (count($content['paragraphs']) > 1)
                <div class="mt-4 max-w-3xl space-y-2 text-base leading-7">
                    @foreach (array_slice($content['paragraphs'], 1) as $p)<p>{{ $p }}</p>@endforeach
                </div>
            @endif
            @if ($degreePages->isNotEmpty() || ! $isHub)
                <ul class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="Narrow the list">
                    @unless ($isHub)<li><a class="badge-neutral hover:underline" href="{{ route('pakistan.hub') }}">All ({{ $hubPage['count'] ?? '' }})</a></li>@endunless
                    @foreach ($degreePages as $d)
                        <li><a class="badge-neutral hover:underline" href="{{ $d['url'] }}" @if($d['facet'] === $page['facet']) aria-current="page" @endif>{{ \Illuminate\Support\Str::before($d['label'], ' for Pakistani') }} ({{ $d['count'] }})</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    @if (! empty($content['soonest']))
        <section class="container-page pt-8" aria-labelledby="pk-soon">
            <h2 id="pk-soon" class="section-title">Closing soon</h2>
            <ul class="mt-4 grid gap-3 md:grid-cols-3">
                @foreach ($content['soonest'] as $s)
                    <li class="card p-5"><a class="font-semibold text-navy-700 underline" href="{{ $s['url'] }}">{{ $s['name'] }}</a><p class="mt-1 text-sm text-muted">Deadline {{ $s['date'] }}</p></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="container-page py-8" aria-labelledby="pk-list">
        <h2 id="pk-list" class="section-title">{{ $page['count'] }} {{ $page['name'] }}</h2>
        <div class="mt-5 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($cycles as $c)<x-cycle-card :cycle="$c" />@endforeach
        </div>
        <div class="mt-6">{{ $cycles->links('pagination::tailwind') }}</div>
    </section>

    @if ($destinationPages->isNotEmpty())
        <section class="container-page pb-8" aria-labelledby="pk-where">
            <h2 id="pk-where" class="section-title">Scholarships for Pakistani students by country</h2>
            <ul class="mt-4 flex flex-wrap gap-2 text-sm">
                @foreach ($destinationPages as $d)
                    <li><a class="badge-neutral hover:underline" href="{{ $d['url'] }}" @if($d['facet'] === $page['facet']) aria-current="page" @endif>{{ \Illuminate\Support\Str::after($d['label'], 'Pakistani students in ') }} ({{ $d['count'] }})</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="container-page pb-8" aria-labelledby="pk-docs">
        <h2 id="pk-docs" class="section-title">Applying from Pakistan: documents you will usually need</h2>
        <div class="card mt-4 max-w-3xl p-6">
            <ul class="list-disc space-y-2 pl-5 text-base leading-7">
                @foreach ($content['checklist'] as $item)<li>{{ $item }}</li>@endforeach
            </ul>
            <p class="mt-4 text-sm text-muted">Every scholarship asks for something slightly different. The document list on each listing is the one to follow.</p>
            @if (! empty($content['services']))
                <p class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                    @foreach ($content['services'] as [$name, $url])<a class="font-semibold text-navy-700 underline" href="{{ $url }}">{{ $name }}</a>@endforeach
                </p>
            @endif
        </div>
    </section>

    <section class="container-page pb-10" aria-labelledby="pk-faq">
        <h2 id="pk-faq" class="section-title">Quick answers</h2>
        <dl class="mt-4 max-w-3xl space-y-4">
            @foreach ($content['faq'] as [$q, $a])
                <div class="card p-5"><dt class="font-semibold">{{ $q }}</dt><dd class="mt-1 text-base leading-7 text-ink">{{ $a }}</dd></div>
            @endforeach
        </dl>
    </section>
</x-layouts.public>
