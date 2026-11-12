@php
    $crumbs = [['Home', route('home')], ['Scholarships', route('scholarships.index')], [$page['label']]];
    $description = \Illuminate\Support\Str::limit($content['paragraphs'][0], 155);
    $country = $page['type'] === 'in' ? \App\Models\Country::where('slug', $page['slug'])->first() : null;
@endphp
<x-layouts.public :title="$page['title']" :description="$description" :crumbs="$crumbs">
    <x-slot:head>
        <x-jsonld :data="['@type' => 'ItemList', 'name' => $page['title'], 'numberOfItems' => $page['count'], 'itemListElement' => $cycles->getCollection()->values()->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => ($cycles->currentPage() - 1) * $cycles->perPage() + $i + 1, 'url' => route('scholarships.show', [$c->programme->slug, $c->slug]), 'name' => $c->programme->title])->all()]" />
        <x-jsonld :data="['@type' => 'FAQPage', 'mainEntity' => collect($content['faq'])->map(fn ($qa) => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]])->all()]" />
    </x-slot:head>

    <section class="border-b border-line bg-white">
        <div class="container-page py-8">
            <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('scholarships.index') }}">Scholarships</a> / <span aria-current="page">{{ $page['label'] }}</span></nav>
            <h1 class="mt-3 text-3xl font-extrabold sm:text-4xl">{{ $page['title'] }}</h1>
            <p class="mt-3 max-w-3xl text-lg text-muted">{{ $content['paragraphs'][0] }}</p>
            @if (count($content['paragraphs']) > 1)
                <div class="mt-4 max-w-3xl space-y-2 text-base leading-7">
                    @foreach (array_slice($content['paragraphs'], 1) as $p)<p>{{ $p }}</p>@endforeach
                </div>
            @endif
            <p class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                @if ($country && $country->is_destination && $country->guidance_published)<a class="font-semibold text-navy-700 underline" href="{{ route('destinations.show', $country) }}">Study in {{ $country->name }}: costs and guidance</a>@endif
                <a class="font-semibold text-navy-700 underline" href="{{ route('scholarships.index') }}">Search and filter all scholarships</a>
            </p>
        </div>
    </section>

    <section class="container-page py-8" aria-labelledby="landing-list">
        <h2 id="landing-list" class="section-title">{{ $page['count'] }} {{ $page['name'] }}</h2>
        <div class="mt-5 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($cycles as $c)<x-cycle-card :cycle="$c" />@endforeach
        </div>
        <div class="mt-6">{{ $cycles->links('pagination::tailwind') }}</div>
    </section>

    <section class="container-page pb-8" aria-labelledby="landing-faq">
        <h2 id="landing-faq" class="section-title">Quick answers</h2>
        <dl class="mt-4 max-w-3xl space-y-4">
            @foreach ($content['faq'] as [$q, $a])
                <div class="card p-5"><dt class="font-semibold">{{ $q }}</dt><dd class="mt-1 text-base leading-7 text-ink">{{ $a }}</dd></div>
            @endforeach
        </dl>
    </section>

    @if ($neighbours->isNotEmpty())
        <section class="container-page pb-10" aria-labelledby="landing-more">
            <h2 id="landing-more" class="section-title">More ways to browse</h2>
            <ul class="mt-4 flex flex-wrap gap-2 text-sm">
                @foreach ($neighbours as $n)<li><a class="badge-neutral hover:underline" href="{{ $n['url'] }}">{{ $n['label'] }} ({{ $n['count'] }})</a></li>@endforeach
            </ul>
        </section>
    @endif
</x-layouts.public>
