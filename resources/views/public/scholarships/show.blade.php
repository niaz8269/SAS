@php
    $svc = app(\App\Domain\Scholarship\DeadlineService::class);
    $archived = $cycle->publication_state === \App\Enums\PublicationState::Archived;
    $deadlinesByRoute = $cycle->currentDeadlines->groupBy(fn ($d) => $d->route_id ?? 0);
    $institutions = $cycle->universities->isNotEmpty() ? $cycle->universities : $programme->universities;
    $title = $programme->title.' '.$cycle->cycle_key;
@endphp
<x-layouts.public :title="$title" :description="\Illuminate\Support\Str::limit(strip_tags($cycle->summary ?: $programme->summary), 155)">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Scholarships', 'item' => route('scholarships.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $title],
        ]], JSON_UNESCAPED_SLASHES) !!}</script>
    </x-slot:head>

    <section class="border-b border-line bg-white">
        <div class="container-page py-8">
            <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('scholarships.index') }}">Scholarships</a> / <span aria-current="page">{{ $programme->title }}</span></nav>
            @if ($archived)
                <div class="mt-4 rounded-md border border-warn/30 bg-warn-soft px-4 py-3 text-sm text-warn" role="note">
                    <strong>Archived call.</strong> This is a past cycle kept for reference.
                    @if ($newer) <a class="underline" href="{{ route('scholarships.show', [$programme->slug, $newer->slug]) }}">See the {{ $newer->intake_year }} cycle</a>. @endif
                </div>
            @endif
            <div class="mt-3 flex flex-wrap gap-2">
                @if ($cycle->sponsored)<span class="badge-warn">{{ $cycle->sponsored_label ?: 'Sponsored' }}</span>@endif
                <span class="badge-neutral">{{ $cycle->intake_year }} intake{{ $cycle->intake_term ? ' · '.$cycle->intake_term : '' }}</span>
                <span class="badge-green">{{ $cycle->fundingLabel() }}</span>
                @if ($cycle->needsRecheck())<span class="badge-warn">Needs recheck</span>@endif
            </div>
            <h1 class="mt-3 text-3xl font-extrabold sm:text-4xl">{{ $programme->title }}</h1>
            <img src="{{ $programme->imageUrl($cycle) }}" alt="" class="mt-4 aspect-video w-full max-w-xl rounded-xl border border-line object-cover" width="960" height="540">
            <p class="mt-1 text-lg text-muted">{{ $programme->funder?->name }} @if($cycle->destinations->isNotEmpty()) · {{ $cycle->destinations->pluck('name')->implode(', ') }} @endif</p>
            @if ($cycle->summary)<p class="mt-4 max-w-3xl">{{ $cycle->summary }}</p>@endif
        </div>
    </section>

    <div class="container-page grid gap-8 py-8 lg:grid-cols-[1fr_340px]">
        <div class="space-y-8">
            {{-- Full timeline (DIR-07): every stage is shown; a stage the source does not date says so. Routes are never merged into one misleading date. --}}
            @php
                $ms = $cycle->milestones->groupBy('kind');
                $stage = function ($kinds) use ($ms) { return collect($kinds)->flatMap(fn ($k) => $ms->get($k, collect()))->values(); };
            @endphp
            <section aria-labelledby="timeline" class="card p-6">
                <h2 id="timeline" class="text-xl font-bold">Application timeline</h2>
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

            <section aria-labelledby="institutions" class="card p-6">
                <h2 id="institutions" class="text-xl font-bold">Universities and programmes</h2>
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
                @if ($cycle->institutions_note)<p class="mt-3 text-sm">{{ $cycle->institutions_note }}</p>@endif
                @if ($unis->isEmpty() && ! $cycle->institutions_note)<p class="mt-3 text-sm text-muted">Participating universities: not confirmed.</p>@endif
                <h3 class="mt-5 font-semibold">Programmes offered</h3>
                @if ($cycle->programmes_note)<p class="mt-2 text-sm whitespace-pre-line">{{ $cycle->programmes_note }}</p>@else<p class="mt-2 text-sm text-muted">Programmes and fields: not confirmed.</p>@endif
                @if ($cycle->destinations->contains('code', 'CN') && $unis->isNotEmpty())
                    <p class="mt-3 text-sm"><a class="text-navy-700 underline" href="{{ route('programmes.index', ['csc' => 1]) }}">Browse programmes listed as open to Chinese Government Scholarship applicants</a></p>
                @endif
            </section>
            <section aria-labelledby="funding" class="card p-6">
                <h2 id="funding" class="text-xl font-bold">Funding</h2>
                @if ($cycle->funding_summary)<p class="mt-2">{{ $cycle->funding_summary }}</p>@endif
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

            <section aria-labelledby="eligibility" class="card p-6">
                <h2 id="eligibility" class="text-xl font-bold">Eligibility</h2>
                <dl class="mt-3 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="font-semibold">Eligible nationalities</dt><dd>
                        @if ($cycle->any_nationality) Any nationality
                        @elseif ($cycle->nationalities->isNotEmpty()) {{ $cycle->nationalities->pluck('name')->implode(', ') }}
                        @else <span class="text-muted">Not confirmed</span> @endif</dd></div>
                    <div><dt class="font-semibold">Degree levels</dt><dd>{{ $cycle->degreeLevels->pluck('name')->implode(', ') ?: 'Not confirmed' }}</dd></div>
                    <div><dt class="font-semibold">Subjects</dt><dd>{{ $cycle->subjects->pluck('name')->implode(', ') ?: 'Not confirmed' }}</dd></div>
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
                @if ($cycle->eligibility_notes)<p class="mt-3 text-sm">{{ $cycle->eligibility_notes }}</p>@endif
                @if ($cycle->age_experience_notes)<p class="mt-2 text-sm">{{ $cycle->age_experience_notes }}</p>@endif
            </section>

            <section aria-labelledby="requirements" class="card p-6">
                <h2 id="requirements" class="text-xl font-bold">Required documents</h2>
                @if ($cycle->requirements->isEmpty())
                    <p class="mt-2 text-sm text-muted">Not confirmed. See the official source.</p>
                @else
                    <ul class="mt-3 space-y-2 text-sm">@foreach ($cycle->requirements as $r)
                        <li class="flex gap-2"><span aria-hidden="true" class="text-navy-700">&#10003;</span><span>{{ $r->text }} @if(! $r->mandatory)<span class="badge-neutral">optional</span>@endif @if($r->conditional_rule)<span class="text-muted">({{ $r->conditional_rule }})</span>@endif</span></li>
                    @endforeach</ul>
                @endif
            </section>

            <section aria-labelledby="verification" class="card p-6">
                <h2 id="verification" class="text-xl font-bold">Sources and verification</h2>
                <p class="mt-2 text-sm">
                    @if ($cycle->last_verified_at) Last verified against the official source on <strong>{{ $cycle->last_verified_at->format('j F Y') }}</strong>.
                    @else Not yet verified. @endif
                    @if ($cycle->needsRecheck()) <span class="badge-warn">Needs recheck</span> @endif
                </p>
                <ul class="mt-2 space-y-1 text-sm">@foreach ($cycle->evidence as $e)
                    <li>{{ $e->publisher }} <span class="text-muted">({{ str_replace('_', ' ', $e->publisher_type) }}, checked {{ $e->checked_at->format('j M Y') }})</span> —
                        <a class="text-navy-700 underline" href="{{ $e->url }}" rel="noopener nofollow" target="_blank">{{ parse_url($e->url, PHP_URL_HOST) }}</a></li>
                @endforeach</ul>
                <p class="mt-3 text-sm"><a class="text-navy-700 underline" href="{{ route('corrections.create', ['cycle' => $cycle->id]) }}">Report an error or an update</a></p>
            </section>
        </div>

        <aside class="space-y-5" aria-label="Actions">
            <div class="card p-5"><x-deadline :cycle="$cycle" /></div>

            <div class="card space-y-4 p-5">
                <h2 class="text-lg font-bold">What would you like to do?</h2>
                <div>
                    <a href="{{ route('contact', ['category' => 'advisory', 'cycle' => $cycle->id]) }}" class="btn-primary w-full">Get application support</a>
                    <p class="mt-1 text-xs text-muted">Ask our team about this call. We give advice and help with your documents; no guarantee of selection, admission or a visa.</p>
                </div>
                <div>
                    @if ($plan)
                        <a href="{{ route('student.applications.show', $plan) }}" class="btn-outline w-full">Open my application plan</a>
                    @else
                        <form method="POST" action="{{ route('student.applications.store') }}">@csrf<input type="hidden" name="cycle_id" value="{{ $cycle->id }}"><button class="btn-outline w-full">Track this application</button></form>
                    @endif
                    <p class="mt-1 text-xs text-muted">Creates a private checklist in your workspace. It does not mean an application was submitted.</p>
                </div>
                @auth
                    <form method="POST" action="{{ $saved ? route('student.saved.destroy', $cycle) : route('student.saved.store') }}">
                        @csrf @if ($saved) @method('DELETE') @else <input type="hidden" name="cycle_id" value="{{ $cycle->id }}"> @endif
                        <button class="btn-ghost w-full">{{ $saved ? 'Saved — click to remove' : 'Save scholarship' }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost w-full">Log in to save or track</a>
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
