<x-layouts.public description="Find verified, officially sourced scholarships worldwide, track your applications and get expert support at every step.">
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-white to-ivory">
        <div class="container-page grid items-center gap-8 py-12 lg:grid-cols-[1.1fr_0.9fr] lg:py-16">
            <div>
                <p class="eyebrow">Global scholarships. Brighter futures.</p>
                <h1 class="mt-3 text-4xl font-extrabold leading-[1.05] sm:text-5xl lg:text-6xl">Your ambition.<br>A world of possibilities.</h1>
                <p class="mt-5 max-w-xl text-lg text-muted">Discover officially sourced scholarships and get expert support at every step. Browsing is free and never needs an account.</p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('scholarships.index') }}" class="btn-primary btn-lg">Explore Scholarships <span aria-hidden="true">&rarr;</span></a>
                    <a href="{{ route('services.index') }}" class="btn-outline btn-lg">Our services</a>
                </div>
                <ul class="mt-8 grid gap-4 text-sm sm:grid-cols-3">
                    <li class="flex items-start gap-3"><span class="mt-0.5 text-forest-700" aria-hidden="true">&#9679;</span><span><strong>Explore opportunities</strong><br><span class="text-muted">Search by destination, degree and subject.</span></span></li>
                    <li class="flex items-start gap-3"><span class="mt-0.5 text-forest-700" aria-hidden="true">&#9679;</span><span><strong>Get expert guidance</strong><br><span class="text-muted">Advisory and application support.</span></span></li>
                    <li class="flex items-start gap-3"><span class="mt-0.5 text-forest-700" aria-hidden="true">&#9679;</span><span><strong>Take the next step</strong><br><span class="text-muted">Plan and track on official portals.</span></span></li>
                </ul>
            </div>
            <figure class="relative mx-auto w-full max-w-lg" aria-label="Decorative illustration">
                <svg viewBox="0 0 480 400" class="w-full" role="img" aria-label="Stylised globe, arch and campus tower illustration">
                    <defs>
                        <linearGradient id="g1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#dcebdf"/><stop offset="1" stop-color="#bcd8c2"/></linearGradient>
                    </defs>
                    <circle cx="260" cy="190" r="170" fill="url(#g1)"/>
                    <g fill="none" stroke="#2f6a3f" stroke-opacity=".45" stroke-width="1.5"><ellipse cx="260" cy="190" rx="170" ry="60"/><ellipse cx="260" cy="190" rx="90" ry="170"/><path d="M90 190h340M260 20v340"/></g>
                    <g fill="#25573a"><rect x="110" y="250" width="70" height="110" rx="4"/><path d="M110 250l35-50 35 50z"/><rect x="200" y="210" width="60" height="150" rx="4" fill="#1d4630"/><path d="M200 210l30-60 30 60z" fill="#1d4630"/><rect x="280" y="260" width="110" height="100" rx="4"/></g>
                    <g fill="#fbf9f2"><rect x="124" y="275" width="10" height="16" rx="5"/><rect x="156" y="275" width="10" height="16" rx="5"/><rect x="216" y="235" width="10" height="18" rx="5"/><rect x="236" y="235" width="10" height="18" rx="5"/><rect x="300" y="285" width="12" height="20" rx="6"/><rect x="330" y="285" width="12" height="20" rx="6"/><rect x="360" y="285" width="12" height="20" rx="6"/></g>
                    <path d="M40 330h400" stroke="#14301f" stroke-width="3" stroke-linecap="round"/>
                    <path d="M330 70l50 20-18 4-8 16z" fill="#cfe86a" stroke="#14301f" stroke-width="2" stroke-linejoin="round"/>
                </svg>
                <figcaption class="mt-1 text-center text-xs text-muted">Temporary illustration. Licensed photography to be added.</figcaption>
            </figure>
        </div>

        {{-- Search --}}
        <div class="container-page relative -mb-px pb-10">
            <form method="GET" action="{{ route('scholarships.index') }}" class="card p-5 shadow-lg sm:p-6" role="search" aria-labelledby="hero-search">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h2 id="hero-search" class="text-lg font-bold">Search scholarships worldwide</h2>
                    <p class="text-sm text-muted">Find the right opportunities for your goals.</p>
                </div>
                <div class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto] md:items-end">
                    <div><label for="h-dest" class="label">Destination</label>
                        <select id="h-dest" name="destination[]" class="input"><option value="">Any destination</option>@foreach($destinationsAll as $c)<option value="{{ $c->slug }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div><label for="h-deg" class="label">Degree level</label>
                        <select id="h-deg" name="degree[]" class="input"><option value="">Any degree level</option>@foreach($degrees as $d)<option value="{{ $d->slug }}">{{ $d->name }}</option>@endforeach</select></div>
                    <div><label for="h-sub" class="label">Field of study</label>
                        <select id="h-sub" name="subject[]" class="input"><option value="">Any field of study</option>@foreach($subjects as $s)<option value="{{ $s->slug }}">{{ $s->name }}</option>@endforeach</select></div>
                    <button class="btn-primary btn-lg w-full md:w-auto">Find scholarships <span aria-hidden="true">&rarr;</span></button>
                </div>
            </form>
        </div>
    </section>

    {{-- Browse by degree --}}
    @if ($degrees->isNotEmpty())
    <section class="container-page py-10" aria-labelledby="by-degree">
        <h2 id="by-degree" class="section-title">Browse by degree</h2>
        <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($degrees as $d)
                <li><a href="{{ route('scholarships.index', ['degree' => [$d->slug]]) }}" class="card flex items-center justify-between px-5 py-4 font-semibold hover:border-forest-500">{{ $d->name }} <span aria-hidden="true" class="text-forest-700">&rarr;</span></a></li>
            @endforeach
        </ul>
    </section>
    @endif

    {{-- Featured --}}
    <section class="container-page py-10" aria-labelledby="featured">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="eyebrow">Featured opportunities</p><h2 id="featured" class="section-title mt-1">Find your next opportunity</h2></div>
            <a href="{{ route('scholarships.index') }}" class="text-sm font-semibold text-forest-700 underline">View all scholarships &rarr;</a>
        </div>
        @if ($featured->isEmpty())
            <p class="card mt-5 p-6 text-sm text-muted">Featured scholarships appear here once our editors have verified and published them. <a class="text-forest-700 underline" href="{{ route('scholarships.index') }}">Browse the directory</a>.</p>
        @else
            <div class="mt-5 grid gap-5 md:grid-cols-2 lg:grid-cols-3">@foreach ($featured as $c)<x-cycle-card :cycle="$c" />@endforeach</div>
        @endif
    </section>

    {{-- Closing soon --}}
    <section class="container-page py-6" aria-labelledby="closing-soon">
        <div class="rounded-xl border border-alert/30 bg-alert-soft p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="closing-soon" class="text-xl font-extrabold text-alert">Closing soon</h2>
                <a href="{{ route('scholarships.index', ['sort' => 'closing']) }}" class="text-sm font-semibold text-alert underline">View upcoming deadlines &rarr;</a>
            </div>
            <p class="mt-1 text-sm text-ink/80">Only calls with a confirmed upcoming closing date are listed. Always check the official page for the latest information.</p>
            @if ($closingSoon->isEmpty())
                <p class="mt-4 text-sm text-muted">No verified deadlines are closing in the next {{ config('sas.deadlines.closing_soon_days') }} days.</p>
            @else
                <div class="mt-4 grid gap-4 md:grid-cols-3">@foreach ($closingSoon as $c)<x-cycle-card :cycle="$c" />@endforeach</div>
            @endif
        </div>
    </section>

    {{-- Destinations --}}
    <section class="container-page py-10" aria-labelledby="destinations">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 id="destinations" class="section-title">Where will your future take you?</h2>
                <p class="mt-1 text-muted">Discover study destinations and the scholarships available there.</p></div>
            <a href="{{ route('destinations.index') }}" class="text-sm font-semibold text-forest-700 underline">Explore all destinations &rarr;</a>
        </div>
        @if ($destinations->isEmpty())
            <p class="card mt-5 p-6 text-sm text-muted">Destination guides will appear here once reviewed and published.</p>
        @else
            <ul class="mt-5 grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ($destinations as $c)
                    <li><a href="{{ route('destinations.show', $c) }}" class="group relative flex h-40 items-end overflow-hidden rounded-xl bg-gradient-to-br from-forest-700 to-forest-900 p-4 text-white">
                        <span class="absolute inset-0 opacity-20" style="background: radial-gradient(circle at 80% 20%, #cfe86a, transparent 55%)" aria-hidden="true"></span>
                        <span class="relative text-lg font-bold group-hover:underline">{{ $c->name }} <span aria-hidden="true">&rarr;</span></span></a></li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Services --}}
    <section class="bg-forest-50/60 py-12" aria-labelledby="services">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><h2 id="services" class="section-title">Support beyond the scholarship</h2><p class="mt-1 text-muted">Professional help at every step of your study abroad journey.</p></div>
                <a href="{{ route('services.index') }}" class="text-sm font-semibold text-forest-700 underline">Learn more about our services &rarr;</a>
            </div>
            @if ($services->isEmpty())
                <p class="card mt-5 p-6 text-sm text-muted">Service pages will appear here once published. You can still <a class="text-forest-700 underline" href="{{ route('contact') }}">send us an enquiry</a>.</p>
            @else
                <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($services as $s)
                        <li><a href="{{ route('services.show', $s) }}" class="card block h-full p-5 hover:border-forest-500"><h3 class="font-bold">{{ $s->name }}</h3><p class="mt-1 text-sm text-muted">{{ $s->summary }}</p></a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- How it works --}}
    <section class="container-page py-12" aria-labelledby="how">
        <h2 id="how" class="section-title">How the platform works</h2>
        <ol class="mt-6 grid gap-5 md:grid-cols-3">
            @foreach ([['1', 'Discover', 'Search verified scholarship calls. Every call shows its official source and when it was last checked.'], ['2', 'Plan', 'Save scholarships, build an application checklist and keep documents in a private vault. You apply on the official website.'], ['3', 'Get support', 'Ask for advice, writing help, document evaluation, attestation or visa guidance when you need it.']] as [$n, $t, $b])
                <li class="card p-6"><span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-lime font-bold text-forest-900" aria-hidden="true">{{ $n }}</span><h3 class="mt-3 text-lg font-bold">{{ $t }}</h3><p class="mt-1 text-sm text-muted">{{ $b }}</p></li>
            @endforeach
        </ol>
    </section>

    {{-- Resources --}}
    <section class="container-page pb-10" aria-labelledby="resources">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="resources" class="section-title">Latest resources</h2>
            <a href="{{ route('resources.index') }}" class="text-sm font-semibold text-forest-700 underline">All guides &rarr;</a>
        </div>
        @if ($articles->isEmpty())
            <p class="card mt-5 p-6 text-sm text-muted">Guides and articles will appear here once published.</p>
        @else
            <div class="mt-5 grid gap-5 md:grid-cols-3">
                @foreach ($articles as $a)
                    <article class="card p-5"><p class="eyebrow">{{ ucfirst($a->category) }}</p><h3 class="mt-1 text-lg font-bold"><a class="hover:underline" href="{{ route('resources.show', $a) }}">{{ $a->title }}</a></h3><p class="mt-1 text-sm text-muted">{{ $a->excerpt }}</p></article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Consultation --}}
    <section class="container-page pb-4">
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-forest-800 p-8 text-white">
            <div><h2 class="text-2xl font-extrabold !text-white">Let's plan your next chapter.</h2><p class="mt-1 text-forest-100">Request a one-on-one consultation. Our team will confirm a time with you. A request is not a confirmed booking.</p></div>
            <a href="{{ route('consultations.create') }}" class="btn-lime btn-lg">Book a Consultation <span aria-hidden="true">&rarr;</span></a>
        </div>
    </section>
</x-layouts.public>