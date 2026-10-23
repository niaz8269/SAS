@php
    $svc = app(\App\Domain\Scholarship\DeadlineService::class);
    $archived = $cycle->publication_state === \App\Enums\PublicationState::Archived;
    $deadlinesByRoute = $cycle->currentDeadlines->groupBy(fn ($d) => $d->route_id ?? 0);
    $institutions = $cycle->universities->isNotEmpty() ? $cycle->universities : $programme->universities;
    $title = $programme->title.' '.$cycle->cycle_key;
    $firstRoute = $cycle->routes->first(fn ($r) => filled($r->application_url));
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
            <p class="mt-1 text-lg text-muted">{{ $programme->funder?->name }} @if($cycle->destinations->isNotEmpty()) · {{ $cycle->destinations->pluck('name')->implode(', ') }} @endif</p>
            @if ($cycle->summary)<p class="mt-4 max-w-3xl">{{ $cycle->summary }}</p>@endif
        </div>
    </section>

    <div class="container-page grid gap-8 py-8 lg:grid-cols-[1fr_340px]">
        <div class="space-y-8">
            {{-- Deadlines per route (DIR-07): routes are never merged into one misleading date. --}}
            <section aria-labelledby="deadlines" class="card p-6">
                <h2 id="deadlines" class="text-xl font-bold">Deadlines and application routes</h2>
                @if ($cycle->routes->isEmpty() && $cycle->currentDeadlines->isEmpty())
                    <p class="mt-3 text-sm text-muted">Not confirmed. Check the official source below.</p>
                @endif
                @foreach ($cycle->routes as $route)
                    @php($rd = $deadlinesByRoute->get($route->id)?->first())
                    <div class="mt-4 rounded-lg border border-line p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-semibold">{{ $route->name }} <span class="badge-neutral ml-1">{{ str_replace('_', ' ', $route->route_type) }}</span></h3>
                            @if ($route->application_url)
                                <a href="{{ route('scholarships.out', $route) }}" rel="noopener nofollow" target="_blank" class="btn-primary !py-1.5">Apply on official website <span class="text-xs font-normal opacity-80">({{ $route->application_domain }})</span></a>
                            @endif
                        </div>
                        @if ($rd)
                            @php($i = $svc->describe($rd, auth()->user()?->timezone))
                            <p class="mt-2 text-sm"><span class="font-medium">Closing:</span> {{ $i['primary'] }} @if($i['note'])<span class="text-muted">· {{ $i['note'] }}</span>@endif</p>
                            @if ($i['secondary'])<p class="text-xs text-muted">{{ $i['secondary'] }}</p>@endif
                            @if ($rd->source_text)<p class="text-xs text-muted">Published as: “{{ $rd->source_text }}”</p>@endif
                        @else
                            <p class="mt-2 text-sm text-muted">Closing date: Not confirmed</p>
                        @endif
                        @if ($route->fee_notes)<p class="mt-1 text-sm text-muted">Application fee: {{ $route->fee_notes }}</p>@endif
                    </div>
                @endforeach
                @if ($deadlinesByRoute->has(0))
                    @php($gi = $svc->describe($deadlinesByRoute->get(0)->first(), auth()->user()?->timezone))
                    <p class="mt-4 text-sm"><span class="font-medium">Overall deadline:</span> {{ $gi['primary'] }} @if($gi['note'])<span class="text-muted">· {{ $gi['note'] }}</span>@endif</p>
                    @if ($gi['secondary'])<p class="text-xs text-muted">{{ $gi['secondary'] }}</p>@endif
                @endif
                <p class="mt-4 text-xs text-muted">Where the source does not state a closing time we show the date only and do not assume an hour. Different routes can close on different dates.</p>
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
                    @if ($firstRoute)
                        <a href="{{ route('scholarships.out', $firstRoute) }}" target="_blank" rel="noopener nofollow" class="btn-primary w-full">Apply on official website</a>
                        <p class="mt-1 text-xs text-muted">Opens {{ $firstRoute->application_domain }} in a new tab. You apply there; SAS does not submit anything for you.</p>
                    @else
                        <p class="text-sm text-muted">No official application link has been confirmed yet. Use the source links below.</p>
                    @endif
                </div>
                <div>
                    @if ($plan)
                        <a href="{{ route('student.applications.show', $plan) }}" class="btn-outline w-full">Open my application plan</a>
                    @else
                        <form method="POST" action="{{ route('student.applications.store') }}">@csrf<input type="hidden" name="cycle_id" value="{{ $cycle->id }}"><button class="btn-outline w-full">Track this application</button></form>
                    @endif
                    <p class="mt-1 text-xs text-muted">Creates a private checklist in your workspace. It does not mean an application was submitted.</p>
                </div>
                <div>
                    <a href="{{ route('contact', ['category' => 'writing', 'cycle' => $cycle->id]) }}" class="btn-gold w-full">Get application assistance</a>
                    <p class="mt-1 text-xs text-muted">Send an enquiry about advice or writing help for this call. No guarantee of selection.</p>
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
