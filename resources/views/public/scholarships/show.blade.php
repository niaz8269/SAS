@php
    $svc = app(\App\Domain\Scholarship\DeadlineService::class);
    $archived = $cycle->publication_state === \App\Enums\PublicationState::Archived;
    $deadlinesByRoute = $cycle->currentDeadlines->groupBy(fn ($d) => $d->route_id ?? 0);
    $institutions = $cycle->universities->isNotEmpty() ? $cycle->universities : $programme->universities;
    $title = $programme->title.' '.$cycle->keyLabel();
    $shortTitle = \Illuminate\Support\Str::limit($programme->title, 48, '…', true); // search results show about 60 characters: the full name stays in the heading
    // Two scholarships of one programme family differ only at the end ("..., Republic of Chile" and "..., Nelson Mandela"): keep that end so their titles stay different.
    if (mb_strlen($programme->title) > 48 && str_contains($programme->title, ', ') && mb_strlen($tail = trim(preg_replace('/\s*\(.*\)\s*$/', '', substr(strrchr($programme->title, ','), 1)))) <= 22) {
        $shortTitle = \Illuminate\Support\Str::limit(trim(substr($programme->title, 0, strrpos($programme->title, ','))), 46 - mb_strlen($tail), '…', true).', '.$tail;
    }
    $metaTitle = $shortTitle.' '.$cycle->intake_year;
    if (mb_strlen($metaTitle.': deadline and requirements') <= 58) { $metaTitle .= ': deadline and requirements'; } // the two things people search for, added only when the title is short enough
    $facts = app(\App\Domain\Seo\ScholarshipFacts::class);
    $glance = $facts->glance($cycle);
    $faq = $facts->faq($cycle);
    $landing = app(\App\Domain\Seo\Landing::class)->pages();
    // Landing pages this scholarship belongs to: its destinations and degree levels (only those that have a page).
    $degreeWords = ['bachelor' => 'bachelor', 'master' => 'masters', 'doctorate' => 'phd', 'postdoctoral' => 'postdoctoral', 'diploma' => 'diploma'];
    $browse = $landing->filter(fn ($p) => ($p['type'] === 'in' && $cycle->destinations->contains('slug', $p['slug']))
        || ($p['type'] === 'for' && $cycle->degreeLevels->contains(fn ($d) => ($degreeWords[$d->code] ?? null) === $p['slug']))
        || ($p['type'] === 'funding' && $p['slug'] === 'fully-funded' && $cycle->fundingLabel() === 'Fully funded')
        || ($p['type'] === 'subject' && $cycle->subjects->contains('slug', $p['slug']))
        || ($p['type'] === 'from' && ! $cycle->any_nationality && $cycle->nationalities->contains('slug', $p['slug']))
        || ($p['type'] === 'open' && $cycle->any_nationality)
        || ($p['type'] === 'pk' && $p['facet'] === null && app(\App\Domain\Seo\PakistanHub::class)->isEligible($cycle))
        || ($p['type'] === 'guide' && in_array($cycle->id, $p['ids'], true)))->values();
    $guides = $cycle->destinations->filter(fn ($d) => $d->is_destination && $d->guidance_published);
@endphp
<x-layouts.public :title="$metaTitle" :description="\App\Support\Html::text($cycle->summary ?: $programme->summary, 155) ?: $facts->description($cycle)">
    <x-slot:head>
        @if ($faq)<x-jsonld :data="['@type' => 'FAQPage', 'mainEntity' => collect($faq)->map(fn ($qa) => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]])->all()]" />@endif
        <script type="application/ld+json">{!! json_encode(['@@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Scholarships', 'item' => route('scholarships.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $title],
        ]], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    </x-slot:head>

    @php
        $next = $svc->nextClosing($cycle)?->format('j F Y');
        $facts = array_filter([
            'Funder' => $programme->funder?->name,
            'Where' => $cycle->destinations->isNotEmpty() ? $cycle->destinations->pluck('name')->take(3)->implode(', ').($cycle->destinations->count() > 3 ? ' +'.($cycle->destinations->count() - 3) : '') : null,
            'Degree levels' => $cycle->degreeLevels->pluck('name')->implode(', ') ?: null,
            'Funding' => $cycle->fundingLabel() === 'Funding details not confirmed' ? null : $cycle->fundingLabel(),
            'Next closing date' => $next,
        ]);
        $jump = array_filter(['timeline' => 'Timeline', 'institutions' => 'Programmes', 'funding' => 'Funding', 'eligibility' => 'Eligibility', 'requirements' => 'Documents', 'process' => $cycle->applicationSteps->isNotEmpty() ? 'How to apply' : null, 'quick-answers' => $faq ? 'Quick answers' : null, 'verification' => 'Sources']);
    @endphp
    <section class="border-b border-line bg-gradient-to-b from-navy-50/70 to-white">
        <div class="container-page py-8">
            <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('scholarships.index') }}">Scholarships</a> / <span aria-current="page">{{ $programme->title }}</span></nav>
            @if ($archived)
                <div class="mt-4 rounded-md border border-warn/30 bg-warn-soft px-4 py-3 text-sm text-warn" role="note">
                    <strong>Archived call.</strong> This is a past cycle kept for reference.
                    @if ($newer) <a class="underline" href="{{ route('scholarships.show', [$programme->slug, $newer->slug]) }}">See the {{ $newer->intake_year }} cycle</a>. @endif
                </div>
            @endif
            <div class="mt-4 grid gap-8 lg:grid-cols-[1fr_19rem] lg:items-start">
                <div class="min-w-0">
                    <div class="flex flex-wrap gap-2">
                        @if ($cycle->sponsored)<span class="badge-warn">{{ $cycle->sponsored_label ?: 'Sponsored' }}</span>@endif
                        <span class="badge-neutral">{{ $cycle->intake_year }} intake{{ $cycle->intake_term ? ' · '.$cycle->intake_term : '' }}</span>
                        <span class="badge-green">{{ $cycle->fundingSummaryLabel() }}</span>
                        @if ($cycle->needsRecheck())<span class="badge-warn">Needs recheck</span>@endif
                    </div>
                    <h1 class="mt-3 text-3xl font-extrabold leading-tight sm:text-4xl">{{ $programme->title }}</h1>
                    @if ($cycle->summary)<x-readmore height="9rem" class="mt-4 max-w-3xl"><div class="rich text-base leading-7">{!! \App\Support\Html::rich($cycle->summary) !!}</div></x-readmore>@endif
                    @if ($facts)
                        <h2 class="mt-6 text-xs font-semibold uppercase tracking-wide text-muted">At a glance</h2>
                        <dl class="mt-2 grid max-w-3xl grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($facts as $label => $value)
                                <div class="rounded-lg border border-line bg-white px-3 py-2.5"><dt class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $label }}</dt><dd class="mt-0.5 text-sm font-semibold text-ink">{{ $value }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                    <p class="mt-4 max-w-3xl text-sm leading-6 text-muted">{{ $glance }}</p>
                    @if ($browse->isNotEmpty() || $guides->isNotEmpty())
                        <p class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                            @foreach ($guides as $g)<a class="font-semibold text-navy-700 underline" href="{{ route('destinations.show', $g) }}">Study in {{ $g->name }}: costs and guidance</a>@endforeach
                            @foreach ($browse as $b)<a class="font-semibold text-navy-700 underline" href="{{ $b['url'] }}">{{ $b['type'] === 'guide' ? $b['label'].': requirements and deadlines' : ($b['type'] === 'pk' ? 'Open to Pakistani students: more scholarships' : 'More '.mb_strtolower($b['label'])) }}</a>@endforeach
                        </p>
                    @endif
                </div>
                <img src="{{ $programme->imageUrl($cycle) }}" alt="" class="hidden aspect-[4/3] w-full rounded-xl border border-line object-cover shadow-sm lg:block" width="640" height="480">
            </div>
        </div>
    </section>

    {{-- A menu that stays in view while reading, so a long page is never a wall: every section is one click away. --}}
    <nav aria-label="On this page" class="sticky z-30 border-b border-line bg-white/95 backdrop-blur" style="top: var(--site-header, 4rem)">
        <div class="container-page flex gap-1 overflow-x-auto py-2 text-sm [scrollbar-width:thin]">
            @foreach ($jump as $id => $label)<a href="#{{ $id }}" onclick="var e=document.getElementById('{{ $id }}');var d=e&&e.closest('details');if(d){d.open=true}" class="shrink-0 rounded-full px-3 py-1.5 font-medium text-ink hover:bg-navy-50 hover:text-navy-700">{{ $label }}</a>@endforeach
        </div>
    </nav>
    <div class="container-page grid grid-cols-[minmax(0,1fr)] gap-8 py-8 lg:grid-cols-[minmax(0,1fr)_21.25rem]">
        <div class="min-w-0 space-y-8">
            {{-- Full timeline (DIR-07): every stage is shown; a stage the source does not date says so. Routes are never merged into one misleading date. --}}
            @php
                $ms = $cycle->milestones->groupBy('kind');
                $stage = function ($kinds) use ($ms) { return collect($kinds)->flatMap(fn ($k) => $ms->get($k, collect()))->values(); };
            @endphp
            <section aria-labelledby="timeline" class="card p-6">
                <h2 id="timeline" class="scroll-mt-36 text-xl font-bold">Application timeline</h2>
                <ol class="mt-4 space-y-3">
                    <li class="rounded-lg border border-line p-4">
                        <h3 class="font-semibold">Applications open</h3>
                        @forelse ($stage(['opens']) as $m)
                            <p class="mt-1 text-sm">@if ($m->label)<span class="text-muted">{{ $m->label }}:</span> @endif{{ $m->display() }}</p>
                            @if ($m->source_text)<p class="text-xs text-muted">Published as: “{{ $m->source_text }}”</p>@endif
                        @empty
                            <p class="mt-1 text-sm text-muted">Not announced yet</p>
                        @endforelse
                    </li>
                    <li class="rounded-lg border border-line p-4">
                        <h3 class="font-semibold">Application deadline</h3>
                        @if ($deadlinesByRoute->has(0))
                            @php($gi = $svc->describe($deadlinesByRoute->get(0)->first(), auth()->user()?->timezone))
                            <p class="mt-1 text-sm">{{ $gi['primary'] }} @if($gi['note'])<span class="text-muted">· {{ $gi['note'] }}</span>@endif</p>
                            @if ($gi['secondary'])<p class="text-xs text-muted">{{ $gi['secondary'] }}</p>@endif
                            @if ($deadlinesByRoute->get(0)->first()->source_text)<p class="text-xs text-muted">Published as: “{{ $deadlinesByRoute->get(0)->first()->source_text }}”</p>@endif
                        @endif
                        @foreach ($cycle->routes as $route)
                            @php($rd = $deadlinesByRoute->get($route->id)?->first())
                            @if ($rd)
                                @php($i = $svc->describe($rd, auth()->user()?->timezone))
                                <p class="mt-1 text-sm">@if ($cycle->routes->count() > 1 || $deadlinesByRoute->has(0))<span class="text-muted">{{ $route->name }}:</span> @endif{{ $i['primary'] }} @if($i['note'])<span class="text-muted">· {{ $i['note'] }}</span>@endif</p>
                                @if ($i['secondary'])<p class="text-xs text-muted">{{ $i['secondary'] }}</p>@endif
                                @if ($rd->source_text)<p class="text-xs text-muted">Published as: “{{ $rd->source_text }}”</p>@endif
                            @endif
                        @endforeach
                        @if (! $deadlinesByRoute->has(0) && $cycle->routes->every(fn ($r) => ! $deadlinesByRoute->get($r->id)))
                            <p class="mt-1 text-sm text-muted">Not announced yet</p>
                        @endif
                    </li>
                    <li class="rounded-lg border border-line p-4">
                        <h3 class="font-semibold">Interviews and tests</h3>
                        @forelse ($stage(['interview', 'test']) as $m)
                            <p class="mt-1 text-sm"><span class="text-muted">{{ $m->label ?: \App\Models\CycleMilestone::KINDS[$m->kind] }}:</span> {{ $m->display() }}</p>
                            @if ($m->source_text)<p class="text-xs text-muted">Published as: “{{ $m->source_text }}”</p>@endif
                        @empty
                            <p class="mt-1 text-sm text-muted">Not announced yet</p>
                        @endforelse
                    </li>
                    <li class="rounded-lg border border-line p-4">
                        <h3 class="font-semibold">Final results</h3>
                        @forelse ($stage(['results']) as $m)
                            <p class="mt-1 text-sm">@if ($m->label)<span class="text-muted">{{ $m->label }}:</span> @endif{{ $m->display() }}</p>
                            @if ($m->source_text)<p class="text-xs text-muted">Published as: “{{ $m->source_text }}”</p>@endif
                        @empty
                            <p class="mt-1 text-sm text-muted">Not announced yet</p>
                        @endforelse
                    </li>
                    @foreach ($stage(['other']) as $m)
                        <li class="rounded-lg border border-line p-4">
                            <h3 class="font-semibold">{{ $m->label ?: 'Other date' }}</h3>
                            <p class="mt-1 text-sm">{{ $m->display() }}</p>
                            @if ($m->source_text)<p class="text-xs text-muted">Published as: “{{ $m->source_text }}”</p>@endif
                        </li>
                    @endforeach
                </ol>
                @foreach ($cycle->routes as $route)
                    @if ($route->fee_notes)<p class="mt-3 text-sm text-muted">{{ $route->name }}: application fee {{ $route->fee_notes }}</p>@endif
                @endforeach
                <p class="mt-4 text-xs text-muted">Where the source does not state a time we show the date only and do not assume an hour. "Not announced yet" means the source gives no date. Dates can change: ask us for the latest.</p>
            </section>

            <details open x-data x-init="if (window.innerWidth < 768) $el.removeAttribute('open')" aria-labelledby="institutions" class="group card p-6">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 md:pointer-events-none md:cursor-default [&::-webkit-details-marker]:hidden"><h2 id="institutions" class="scroll-mt-36 text-xl font-bold">Universities and programmes</h2><span aria-hidden="true" class="shrink-0 text-2xl leading-none text-navy-700 transition group-open:rotate-45 md:hidden">+</span></summary>
                @php($unis = $institutions)
                @if ($unis->isNotEmpty())
                    <h3 class="mt-3 font-semibold">Universities included</h3>
                    <ul class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                        @foreach ($unis->take(12) as $u)
                            <li>@if ($u->published && $u->csc_key)<a class="text-navy-700 underline" href="{{ route('universities.show', $u) }}">{{ $u->name }}</a>@else{{ $u->name }}@endif</li>
                        @endforeach
                    </ul>
                    @if ($unis->count() > 12)<p class="mt-2 text-sm text-muted">and {{ $unis->count() - 12 }} more. <a class="text-navy-700 underline" href="{{ route('universities.index') }}">Browse all universities</a>.</p>@endif
                @endif
                @if ($cycle->institutions_note)<x-readmore height="7rem" class="mt-3"><div class="rich text-sm leading-6">{!! \App\Support\Html::rich($cycle->institutions_note) !!}</div></x-readmore>@endif
                @if ($unis->isEmpty() && ! $cycle->institutions_note)<p class="mt-3 text-sm text-muted">Participating universities: not confirmed.</p>@endif
                <h3 class="mt-5 font-semibold">Degrees and programmes offered</h3>
                {{-- Only counts and the first programmes per level are read here: a scholarship can name thousands (the full list is a page of its own). --}}
                <?php
                    $summaries = $cycle->offerings()->whereNull('name')->get()->keyBy('degree_level_id');
                    $counts = $cycle->offerings()->whereNotNull('name')->selectRaw('degree_level_id, count(*) as n')->groupBy('degree_level_id')->pluck('n', 'degree_level_id');
                    $levelIds = $summaries->keys()->merge($counts->keys())->unique()->sort()->values();
                    $levelModels = \App\Models\DegreeLevel::whereIn('id', $levelIds)->get()->keyBy('id');
                    $firstTab = $levelIds->first();
                ?>
                @if ($levelIds->isNotEmpty())
                    <div x-data="{ tab: {{ (int) $firstTab }} }" class="mt-3">
                        @if ($levelIds->count() > 1)
                            <div role="tablist" aria-label="Degree level" class="flex flex-wrap gap-2 border-b border-line pb-3">
                                @foreach ($levelIds as $levelId)
                                    <button type="button" role="tab" :aria-selected="tab === {{ (int) $levelId }}" @click="tab = {{ (int) $levelId }}"
                                        :class="tab === {{ (int) $levelId }} ? 'bg-navy-700 text-white' : 'bg-navy-50 text-navy-800 hover:bg-navy-100'" class="rounded-full px-4 py-1.5 text-sm font-semibold">
                                        {{ $levelModels[$levelId]->name }}@if (($counts[$levelId] ?? 0) > 0)<span class="ml-1 font-normal opacity-80">{{ number_format($counts[$levelId]) }}</span>@endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        @foreach ($levelIds as $levelId)
                            <?php
                                $level = $levelModels[$levelId];
                                $summary = $summaries->get($levelId);
                                $total = (int) ($counts[$levelId] ?? 0);
                                $named = $total ? $cycle->offerings()->where('degree_level_id', $levelId)->whereNotNull('name')->orderBy('sort')->orderBy('id')->limit(12)->get() : collect();
                            ?>
                            <div role="tabpanel" @if ($levelIds->count() > 1) x-show="tab === {{ (int) $levelId }}" @if (! $loop->first) x-cloak @endif @endif class="pt-4">
                                @if ($levelIds->count() === 1)<h4 class="font-semibold">{{ $level->name }}</h4>@endif
                                @if ($summary?->detail)<p class="max-w-3xl text-sm leading-6 text-ink">{{ $summary->detail }}</p>@endif
                                @if ($named->isNotEmpty())
                                    <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-muted">{{ number_format($total) }} {{ \Illuminate\Support\Str::plural('programme', $total) }} listed{{ $total > 12 ? ', first 12 shown' : '' }}</p>
                                    <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                                        @foreach ($named as $o)
                                            <li class="rounded-lg border border-line bg-white px-3 py-2 text-sm"><span class="font-medium text-ink">{{ $o->name }}</span>@if ($o->institution)<span class="mt-0.5 block text-xs text-muted">{{ $o->institution }}</span>@endif</li>
                                        @endforeach
                                    </ul>
                                    @if ($total > 12)
                                        <p class="mt-3"><a class="inline-flex items-center gap-1 rounded-lg bg-navy-700 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800" href="{{ route('scholarships.majors', [$programme->slug, $cycle->slug, 'level' => $level->slug]) }}">See all {{ number_format($total) }} {{ $level->name }} programmes and search them &rarr;</a></p>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                @elseif ($cycle->degreeLevels->isNotEmpty())
                    <p class="mt-2 text-sm">Degree levels: {{ $cycle->degreeLevels->pluck('name')->implode(', ') }}. Programme details: not confirmed.</p>
                @endif                <?php $noOfferings = $levelIds->isEmpty(); ?>
                @if ($cycle->programmes_note)<x-readmore height="7rem" class="mt-4"><div class="rich text-sm leading-6">{!! \App\Support\Html::rich($cycle->programmes_note) !!}</div></x-readmore>@elseif ($noOfferings)<p class="mt-2 text-sm text-muted">Programmes and fields: not confirmed.</p>@endif
                @if (\App\Domain\Catalogue\CscCatalogue::appliesTo($cycle))
                    <?php $cscCounts = array_filter(app(\App\Domain\Catalogue\CscCatalogue::class)->counts()); $cscNames = \App\Domain\Catalogue\CscCatalogue::levelNames(); ?>
                    @if ($cscCounts)
                        <div class="mt-5 rounded-lg border border-line bg-navy-50/50 p-4">
                            <h4 class="font-semibold">Programmes open to Chinese Government Scholarship applicants</h4>
                            <p class="mt-1 text-sm leading-6 text-muted">{{ number_format(array_sum($cscCounts)) }} programmes at {{ number_format(app(\App\Domain\Catalogue\CscCatalogue::class)->universities()) }} universities in China are listed by the Study in China system as open to Chinese Government Scholarship applicants. Search them by your major.</p>
                            <ul class="mt-3 flex flex-wrap gap-2">
                                @foreach ($cscCounts as $slug => $n)
                                    <li><a class="inline-block rounded-full bg-white px-3 py-1 text-sm font-semibold text-navy-800 ring-1 ring-line hover:bg-navy-100" href="{{ route('scholarships.majors', [$programme->slug, $cycle->slug, 'level' => $slug]) }}">{{ $cscNames[$slug] }} <span class="font-normal text-muted">{{ number_format($n) }}</span></a></li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <p class="mt-3 text-sm"><a class="text-navy-700 underline" href="{{ route('programmes.index', ['csc' => 1]) }}">Browse programmes listed as open to Chinese Government Scholarship applicants</a></p>
                    @endif
                @endif
            </details>
            <section aria-labelledby="funding" class="card p-6">
                <h2 id="funding" class="scroll-mt-36 text-xl font-bold">Funding</h2>
                @if ($cycle->funding_summary)<x-readmore height="8rem" class="mt-2"><div class="rich text-sm leading-6">{!! \App\Support\Html::rich($cycle->funding_summary) !!}</div></x-readmore>@endif
                @if ($cycle->benefits->isEmpty())
                    <p class="mt-2 text-sm text-muted">Funding components: Not confirmed.</p>
                @else
                    <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm">
                        <caption class="sr-only">Funding components</caption>
                        <thead><tr class="border-b border-line text-muted"><th scope="col" class="py-2 pr-3">Benefit</th><th scope="col" class="py-2 pr-3">Coverage</th><th scope="col" class="py-2">Limits and conditions</th></tr></thead>
                        <tbody>@foreach ($cycle->benefits as $b)
                            <tr class="border-b border-line/60 align-top"><td class="py-2 pr-3 font-medium">{{ ucfirst(str_replace('_', ' ', $b->type)) }}</td>
                            <td class="py-2 pr-3">{{ match($b->coverage) { 'covered' => 'Covered', 'partial' => 'Partly covered', 'not_covered' => 'Not covered', default => 'Not confirmed' } }}</td>
                            <td class="py-2">{{ trim(($b->limit_text ?? '').' '.($b->conditions ?? '')) ?: '—' }}</td></tr>
                        @endforeach</tbody></table></div>
                @endif
                @if ($cycle->funding_type === 'full' && ! $cycle->fully_funded_verified)<p class="mt-2 text-xs text-muted">Full funding has not been verified for this call, so it is not labelled "Fully funded".</p>@endif
            </section>

            <details open x-data x-init="if (window.innerWidth < 768) $el.removeAttribute('open')" aria-labelledby="eligibility" class="group card p-6">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 md:pointer-events-none md:cursor-default [&::-webkit-details-marker]:hidden"><h2 id="eligibility" class="scroll-mt-36 text-xl font-bold">Eligibility</h2><span aria-hidden="true" class="shrink-0 text-2xl leading-none text-navy-700 transition group-open:rotate-45 md:hidden">+</span></summary>
                <dl class="mt-3 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="font-semibold">Eligible nationalities</dt><dd>
                        @if ($cycle->any_nationality) Any nationality{{ $cycle->excludedNationalities->isNotEmpty() ? ' except '.$cycle->excludedNationalities->pluck('name')->implode(', ') : '' }}
                        @elseif ($cycle->nationalities->isNotEmpty()) {{ $cycle->nationalities->pluck('name')->implode(', ') }}
                        @else <span class="text-muted">Not confirmed</span> @endif</dd></div>
                    <div><dt class="font-semibold">Degree levels</dt><dd>{{ $cycle->degreeLevels->pluck('name')->implode(', ') ?: 'Not confirmed' }}</dd></div>
                    <div><dt class="font-semibold">Subjects</dt><dd>{{ $cycle->any_subject ? 'Any subject' : ($cycle->subjects->pluck('name')->implode(', ') ?: 'Not confirmed') }}</dd></div>
                    <div><dt class="font-semibold">Teaching language</dt><dd>{{ $cycle->teaching_language ?: 'Not confirmed' }}</dd></div>
                    <div><dt class="font-semibold">Delivery mode</dt><dd>{{ $cycle->delivery_mode === 'unknown' ? 'Not confirmed' : ucfirst(str_replace('_', ' ', $cycle->delivery_mode)) }}</dd></div>
                    <div><dt class="font-semibold">Host institutions</dt><dd>{{ $institutions->pluck('name')->implode(', ') ?: 'Varies by route' }}</dd></div>
                </dl>
                @if ($cycle->criteria->isNotEmpty())
                    <h3 class="mt-4 font-semibold">Published criteria</h3>
                    <ul class="mt-1 list-disc pl-5 text-sm">@foreach ($cycle->criteria as $c)
                        <li>{{ match($c->type) { 'min_gpa' => 'Academic grade', 'language' => 'Language: '.$c->unit, 'age' => 'Age', default => ucfirst($c->type) } }} {{ $c->operator }} {{ $c->value }}@if($c->type === 'min_gpa') ({{ $c->unit }})@endif</li>
                    @endforeach</ul>
                @endif
                @if ($cycle->eligibility_notes)<x-readmore height="14rem" class="mt-3"><div class="rich text-sm leading-6">{!! \App\Support\Html::rich($cycle->eligibility_notes) !!}</div></x-readmore>@endif
                @if ($cycle->age_experience_notes)<x-readmore height="7rem" class="mt-3"><div class="rich text-sm leading-6">{!! \App\Support\Html::rich($cycle->age_experience_notes) !!}</div></x-readmore>@endif
            </details>

            <details open x-data x-init="if (window.innerWidth < 768) $el.removeAttribute('open')" aria-labelledby="requirements" class="group card p-6">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 md:pointer-events-none md:cursor-default [&::-webkit-details-marker]:hidden"><h2 id="requirements" class="scroll-mt-36 text-xl font-bold">Required documents</h2><span aria-hidden="true" class="shrink-0 text-2xl leading-none text-navy-700 transition group-open:rotate-45 md:hidden">+</span></summary>
                @if ($cycle->requirements->isEmpty())
                    <p class="mt-2 text-sm text-muted">Not confirmed. See the official source.</p>
                @else
                    <ul class="mt-3 space-y-2 text-sm">@foreach ($cycle->requirements as $r)
                        <li class="flex gap-2"><span aria-hidden="true" class="text-navy-700">&#10003;</span><span>{{ $r->text }} @if(! $r->mandatory)<span class="badge-neutral">optional</span>@endif @if($r->conditional_rule)<span class="text-muted">({{ $r->conditional_rule }})</span>@endif</span></li>
                    @endforeach</ul>
                @endif
            </details>

            @php($stepsByPhase = $cycle->applicationSteps->groupBy('phase'))
            @if ($stepsByPhase->isNotEmpty())
                <section aria-labelledby="process" class="card p-6">
                    <h2 id="process" class="scroll-mt-36 text-xl font-bold">Application process</h2>
                    @foreach (\App\Models\CycleApplicationStep::PHASES as $phase => $heading)
                        @if ($stepsByPhase->has($phase))
                            <h3 class="mt-4 font-semibold">{{ $heading }}</h3>
                            <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm leading-6">
                                @foreach ($stepsByPhase[$phase] as $step)
                                    <li><span class="font-medium">{{ $step->title }}</span>@if ($step->detail)<span class="text-ink">: {{ $step->detail }}</span>@endif</li>
                                @endforeach
                            </ol>
                        @endif
                    @endforeach
                </section>
            @endif

            @if ($faq)
                <section aria-labelledby="quick-answers" class="card p-6">
                    <h2 id="quick-answers" class="scroll-mt-36 text-xl font-bold">Quick answers</h2>
                    <div class="mt-3 divide-y divide-line rounded-lg border border-line">
                        @foreach ($faq as [$q, $a])
                            <details class="group px-4 py-3" @if ($loop->first) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold text-ink marker:content-none"><span>{{ $q }}</span><span aria-hidden="true" class="shrink-0 text-navy-700 transition group-open:rotate-45">+</span></summary>
                                <p class="mt-2 text-sm leading-6 text-ink">{{ $a }}</p>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <aside class="space-y-5" aria-label="Actions">
            <div class="card p-5"><x-deadline :cycle="$cycle" /></div>

            <div class="card space-y-4 p-5">
                <h2 class="text-lg font-bold">Want to apply?</h2>
                <div>
                    @auth
                        @if ($plan)
                            <a href="{{ route('student.applications.show', $plan) }}" class="btn-primary w-full">See my documents for this scholarship</a>
                            <p class="mt-1 text-xs text-muted">You already chose this scholarship.</p>
                        @else
                            <form method="POST" action="{{ route('student.applications.store') }}">@csrf<input type="hidden" name="cycle_id" value="{{ $cycle->id }}"><button class="btn-primary w-full">Apply for this scholarship</button></form>
                            <p class="mt-1 text-xs text-muted">You will see the documents this scholarship needs. Nothing is sent to the funder yet.</p>
                        @endif
                    @else
                        <a href="{{ route('register') }}" class="btn-primary w-full">Create a free account to apply</a>
                        <p class="mt-1 text-xs text-muted">Already have one? <a class="text-navy-700 underline" href="{{ route('login') }}">Log in</a>. Creating an account does not send anything to the funder.</p>
                    @endauth
                </div>
                <div>
                    <a href="{{ route('contact', ['category' => 'advisory', 'cycle' => $cycle->id]) }}" class="btn-outline w-full">Ask our team a question</a>
                    <p class="mt-1 text-xs text-muted">We give advice and help with your documents; no guarantee of selection, admission or a visa.</p>
                </div>
                @auth
                    <form method="POST" action="{{ $saved ? route('student.saved.destroy', $cycle) : route('student.saved.store') }}">
                        @csrf @if ($saved) @method('DELETE') @else <input type="hidden" name="cycle_id" value="{{ $cycle->id }}"> @endif
                        <button class="btn-ghost w-full">{{ $saved ? 'Saved. Click to remove' : 'Save for later' }}</button>
                    </form>
                @endauth
            </div>

            <div class="card p-5">
                <h2 class="text-lg font-bold">How you compare</h2>
                @if ($match)
                    <p class="mt-2"><span class="{{ match($match['result']->value) { 'potential_match' => 'badge-green', 'criterion_not_met' => 'badge-alert', default => 'badge-warn' } }}">{{ $match['result']->label() }}</span></p>
                    <ul class="mt-3 space-y-2 text-sm">@foreach ($match['criteria'] as $c)
                        <li><span class="font-medium">{{ $c['name'] }}:</span> <span class="{{ $c['status'] === 'met' ? 'text-navy-700' : ($c['status'] === 'not_met' ? 'text-alert' : 'text-muted') }}">{{ ['met' => 'Met', 'not_met' => 'Not met', 'unknown' => 'Unknown'][$c['status']] }}</span><br><span class="text-xs text-muted">{{ $c['explanation'] }}</span></li>
                    @endforeach</ul>
                    <p class="mt-3 text-xs text-muted">A rule-based indication against published criteria. It is not a prediction of whether you will be selected.</p>
                @else
                    <p class="mt-2 text-sm text-muted"><a class="text-navy-700 underline" href="{{ route('register') }}">Create a free account</a> and complete your profile to see how your details compare with the published criteria.</p>
                @endif
            </div>
        </aside>
    </div>

    @if ($related->isNotEmpty())
        <section class="container-page pb-6" aria-labelledby="related"><h2 id="related" class="section-title">Related scholarships</h2>
            <div class="mt-4 grid gap-5 md:grid-cols-3">@foreach ($related as $c)<x-cycle-card :cycle="$c" />@endforeach</div></section>
    @endif
</x-layouts.public>
