@php
    $crumbs = [['Home', route('home')], ['Scholarships', route('scholarships.index')], ['Chinese Government Scholarship']];
    $chinaPage = app(\App\Domain\Seo\Landing::class)->forCountry('china');
@endphp
<x-layouts.public :title="$page['title']" :description="$page['description']" :crumbs="$crumbs">
    <x-slot:head>
        <x-jsonld :data="['@type' => 'ItemList', 'name' => 'Chinese Government Scholarship calls', 'numberOfItems' => $page['count'], 'itemListElement' => collect($page['calls'])->values()->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $c['url'], 'name' => $c['title']])->all()]" />
        <x-jsonld :data="['@type' => 'FAQPage', 'mainEntity' => collect($page['faq'])->map(fn ($qa) => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]])->all()]" />
    </x-slot:head>

    <section class="border-b border-line bg-white">
        <div class="container-page py-8">
            <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('scholarships.index') }}">Scholarships</a> / <span aria-current="page">Chinese Government Scholarship</span></nav>
            <h1 class="mt-3 text-3xl font-extrabold sm:text-4xl">{{ $page['title'] }}</h1>
            <p class="mt-3 max-w-3xl text-lg text-muted">{{ $page['intro'][0] }}</p>
            <div class="mt-4 max-w-3xl space-y-2 text-base leading-7">
                @foreach (array_slice($page['intro'], 1) as $p)<p>{{ $p }}</p>@endforeach
            </div>
            <nav aria-label="On this page" class="mt-5 flex flex-wrap gap-2 text-sm">
                <a class="badge-neutral hover:underline" href="#calls">The calls</a>
                <a class="badge-neutral hover:underline" href="#requirements">Requirements</a>
                <a class="badge-neutral hover:underline" href="#deadlines">Deadlines</a>
                <a class="badge-neutral hover:underline" href="#benefits">What it covers</a>
                <a class="badge-neutral hover:underline" href="#how">How to apply</a>
                <a class="badge-neutral hover:underline" href="#faq">Quick answers</a>
            </nav>
        </div>
    </section>

    <div class="container-page space-y-6 py-8">
        <section id="calls" class="card p-6" aria-labelledby="calls-h">
            <h2 id="calls-h" class="text-xl font-bold">The {{ $page['count'] }} calls</h2>
            <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm">
                <caption class="sr-only">Chinese Government Scholarship calls</caption>
                <thead><tr class="border-b border-line text-muted"><th scope="col" class="py-2 pr-3">Call</th><th scope="col" class="py-2 pr-3">Degree levels</th><th scope="col" class="py-2 pr-3">Next confirmed closing date</th><th scope="col" class="py-2">Stored as covered</th></tr></thead>
                <tbody>@foreach ($page['calls'] as $c)
                    <tr class="border-b border-line/60 align-top">
                        <td class="py-2 pr-3"><a class="font-semibold text-navy-700 underline" href="{{ $c['url'] }}">{{ $c['title'] }}</a>@if ($c['funder'])<div class="text-xs text-muted">{{ $c['funder'] }}</div>@endif</td>
                        <td class="py-2 pr-3">{{ implode(', ', $c['levels']) ?: '—' }}</td>
                        <td class="py-2 pr-3">{{ $c['next'] ?? '—' }}</td>
                        <td class="py-2">{{ $c['covers'] ? ucfirst(implode(', ', $c['covers'])) : '—' }}</td>
                    </tr>
                @endforeach</tbody></table></div>
        </section>

        <section id="requirements" class="card p-6" aria-labelledby="req-h">
            <h2 id="req-h" class="text-xl font-bold">Chinese Government Scholarship requirements</h2>
            <p class="mt-2 text-base leading-7">{{ $page['who_text'] }}</p>
            @if ($page['document_text'])<p class="mt-2 text-base leading-7">{{ $page['document_text'] }}</p>@endif
            <div class="mt-4 space-y-5">
                @foreach ($page['calls'] as $c)
                    @if ($c['who'] || $c['documents'])
                        <div>
                            <h3 class="font-semibold"><a class="text-navy-700 underline" href="{{ $c['url'] }}">{{ $c['title'] }}</a></h3>
                            @if ($c['who'])<p class="mt-1 text-sm leading-6">{{ $c['who'] }}</p>@endif
                            @if ($c['documents'])
                                <p class="mt-2 text-sm font-medium">Required documents and conditions</p>
                                <ul class="mt-1 list-disc space-y-1 pl-5 text-sm leading-6">@foreach (array_slice($c['documents'], 0, 12) as $d)<li>{{ $d }}</li>@endforeach</ul>
                                @if (count($c['documents']) > 12)<p class="mt-1 text-sm text-muted">and {{ count($c['documents']) - 12 }} more on the <a class="underline" href="{{ $c['url'] }}">call page</a>.</p>@endif
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </section>

        <section id="deadlines" class="card p-6" aria-labelledby="dl-h">
            <h2 id="dl-h" class="text-xl font-bold">Chinese Government Scholarship deadlines</h2>
            @if ($page['dated'])
                <ul class="mt-3 space-y-1 text-base">@foreach ($page['dated'] as $d)<li><a class="text-navy-700 underline" href="{{ $d['url'] }}">{{ $d['title'] }}</a>: closes {{ $d['date'] }}</li>@endforeach</ul>
                <p class="mt-2 text-sm text-muted">Dates are shown without a closing time. Check the call page and the official source before you apply.</p>
            @else
                <p class="mt-2 text-base leading-7">No upcoming closing date is stored for these calls at the moment. Open a call to see its timeline and its official source.</p>
            @endif
        </section>

        <section id="benefits" class="card p-6" aria-labelledby="ben-h">
            <h2 id="ben-h" class="text-xl font-bold">What the Chinese Government Scholarship covers</h2>
            <p class="mt-2 text-base leading-7">{{ $page['cover']['text'] }}</p>
            @foreach ($page['cover']['differences'] as $line)<p class="mt-1 text-sm leading-6">{{ $line }}</p>@endforeach
            <p class="mt-2 text-sm text-muted">{{ $page['cover']['note'] }}</p>
            <div class="mt-4 space-y-5">
                @foreach ($page['calls'] as $c)
                    @if ($c['benefits'])
                        <div>
                            <h3 class="font-semibold"><a class="text-navy-700 underline" href="{{ $c['url'] }}">{{ $c['title'] }}</a></h3>
                            <div class="mt-1 overflow-x-auto"><table class="w-full text-left text-sm">
                                <caption class="sr-only">Funding components of {{ $c['title'] }}</caption>
                                <thead><tr class="border-b border-line text-muted"><th scope="col" class="py-1.5 pr-3">Benefit</th><th scope="col" class="py-1.5 pr-3">Coverage</th><th scope="col" class="py-1.5">Limits and conditions</th></tr></thead>
                                <tbody>@foreach ($c['benefits'] as $b)
                                    <tr class="border-b border-line/60 align-top"><td class="py-1.5 pr-3 font-medium">{{ $b['label'] }}</td>
                                    <td class="py-1.5 pr-3">{{ match($b['coverage']) { 'covered' => 'Covered', 'partial' => 'Partly covered', 'not_covered' => 'Not covered', default => 'Not confirmed' } }}</td>
                                    <td class="py-1.5">{{ $b['limit'] !== '' ? $b['limit'] : '—' }}</td></tr>
                                @endforeach</tbody></table></div>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>

        <section id="how" class="card p-6" aria-labelledby="how-h">
            <h2 id="how-h" class="text-xl font-bold">How to apply for the Chinese Government Scholarship</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-base leading-7">
                <li>Choose the call that matches your degree level in the table above.</li>
                <li>Check who can apply under that call, in particular the nationalities it accepts.</li>
                <li>Collect the required documents listed for it and check each condition.</li>
                <li>Note the closing date on the call page and apply before it, using the official source linked there.</li>
            </ol>
            <p class="mt-3 text-base leading-7">SAS does not decide who receives a scholarship. If you want help preparing your documents or choosing between calls, <a class="font-semibold text-navy-700 underline" href="{{ route('register') }}">create a student account</a> or <a class="font-semibold text-navy-700 underline" href="{{ route('contact') }}">contact SAS</a>.</p>
            @if ($page['sources'])
                <h3 class="mt-4 font-semibold">Official sources</h3>
                <ul class="mt-1 space-y-1 text-sm">@foreach ($page['sources'] as $s)
                    <li>{{ $s['publisher'] }} <span class="text-muted">(checked {{ $s['checked'] }})</span> —
                        <a class="text-navy-700 underline" href="{{ $s['url'] }}" rel="noopener nofollow" target="_blank">{{ parse_url($s['url'], PHP_URL_HOST) }}</a></li>
                @endforeach</ul>
            @endif
        </section>

        <section id="faq" class="space-y-3" aria-labelledby="faq-h">
            <h2 id="faq-h" class="section-title">Quick answers</h2>
            <dl class="max-w-3xl space-y-4">
                @foreach ($page['faq'] as [$q, $a])
                    <div class="card p-5"><dt class="font-semibold">{{ $q }}</dt><dd class="mt-1 text-base leading-7 text-ink">{{ $a }}</dd></div>
                @endforeach
            </dl>
        </section>

        <section aria-labelledby="more-h">
            <h2 id="more-h" class="section-title">More about studying in China</h2>
            <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                @if ($chinaPage)<li><a class="badge-neutral hover:underline" href="{{ $chinaPage['url'] }}">{{ $chinaPage['label'] }} ({{ $chinaPage['count'] }})</a></li>@endif
                @if ($page['china_url'])<li><a class="badge-neutral hover:underline" href="{{ $page['china_url'] }}">Study in China: costs and guidance</a></li>@endif
                <li><a class="badge-neutral hover:underline" href="{{ route('universities.index') }}">Universities</a></li>
                <li><a class="badge-neutral hover:underline" href="{{ route('scholarships.index') }}">All scholarships</a></li>
            </ul>
        </section>
    </div>
</x-layouts.public>
