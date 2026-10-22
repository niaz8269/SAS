@php
    use App\Domain\Site\Site;
    $href = fn ($u) => Site::href($u);
    $media = fn ($p) => \Illuminate\Support\Facades\Storage::disk('public_media')->url($p);
@endphp
<x-layouts.public :description="$sections['hero']->body ?? null">
@foreach ($sections as $s)
@continue(! $s->enabled)
@switch(str_starts_with($s->key, 'custom-') ? 'custom' : $s->key)

@case('hero')
    <section class="relative overflow-hidden bg-gradient-to-b from-white to-ivory">
        <div class="container-page grid items-center gap-8 py-12 lg:grid-cols-[1.1fr_0.9fr] lg:py-16">
            <div>
                @if ($s->eyebrow)<p class="eyebrow">{{ $s->eyebrow }}</p>@endif
                <h1 class="mt-3 text-4xl font-extrabold leading-[1.05] sm:text-5xl lg:text-6xl">{!! nl2br(e($s->heading)) !!}</h1>
                @if ($s->body)<p class="mt-5 max-w-xl text-lg text-muted">{{ $s->body }}</p>@endif
                <div class="mt-7 flex flex-wrap gap-3">
                    @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="btn-primary btn-lg">{{ $s->cta_label }} <span aria-hidden="true">&rarr;</span></a>@endif
                    @if ($s->cta2_label)<a href="{{ $href($s->cta2_url) }}" class="btn-outline btn-lg">{{ $s->cta2_label }}</a>@endif
                </div>
                @if (! empty($s->items))
                <ul class="mt-8 grid gap-4 text-sm sm:grid-cols-3">
                    @foreach ($s->items as $item)
                        <li class="flex items-start gap-3"><span class="mt-0.5 text-forest-700" aria-hidden="true">&#9679;</span><span><strong>{{ $item['title'] ?? '' }}</strong><br><span class="text-muted">{{ $item['text'] ?? '' }}</span></span></li>
                    @endforeach
                </ul>
                @endif
            </div>
            @if ($s->image_path)
                <figure class="relative mx-auto w-full max-w-lg"><img src="{{ $media($s->image_path) }}" alt="" class="w-full rounded-2xl object-cover" loading="eager"></figure>
            @else
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
                <figcaption class="mt-1 text-center text-xs text-muted">Temporary illustration. Upload your own image under Website, Homepage.</figcaption>
            </figure>
            @endif
        </div>

        <div class="container-page relative -mb-px pb-10">
            <form method="GET" action="{{ route('scholarships.index') }}" class="card p-5 shadow-lg sm:p-6" role="search" aria-labelledby="hero-search">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h2 id="hero-search" class="text-lg font-bold">{{ $s->subheading ?: 'Search scholarships' }}</h2>
                    <p class="text-sm text-muted">Find the right opportunities for your goals.</p>
                </div>
                <div class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto] md:items-end">
                    <div><label for="h-dest" class="label">Destination</label>
                        <select id="h-dest" name="destination[]" class="input"><option value="">Any destination</option>@foreach($destinationsAll as $c)<option value="{{ $c->slug }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div><label for="h-deg" class="label">Degree level</label>
                        <select id="h-deg" name="degree[]" class="input"><option value="">Any degree level</option>@foreach($degrees as $d)<option value="{{ $d->slug }}">{{ $d->name }}</option>@endforeach</select></div>
                    <div><label for="h-sub" class="label">Field of study</label>
                        <select id="h-sub" name="subject[]" class="input"><option value="">Any field of study</option>@foreach($subjects as $sub)<option value="{{ $sub->slug }}">{{ $sub->name }}</option>@endforeach</select></div>
                    <button class="btn-primary btn-lg w-full md:w-auto">Find scholarships <span aria-hidden="true">&rarr;</span></button>
                </div>
            </form>
        </div>
    </section>
    @break

@case('degrees')
    @if ($degrees->isNotEmpty())
    <section class="container-page py-10" aria-labelledby="by-degree">
        <h2 id="by-degree" class="section-title">{{ $s->heading }}</h2>
        <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($degrees as $d)
                <li><a href="{{ route('scholarships.index', ['degree' => [$d->slug]]) }}" class="card flex items-center justify-between px-5 py-4 font-semibold hover:border-forest-500">{{ $d->name }} <span aria-hidden="true" class="text-forest-700">&rarr;</span></a></li>
            @endforeach
        </ul>
    </section>
    @endif
    @break

@case('featured')
    <section class="container-page py-10" aria-labelledby="featured">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>@if ($s->eyebrow)<p class="eyebrow">{{ $s->eyebrow }}</p>@endif<h2 id="featured" class="section-title mt-1">{{ $s->heading }}</h2></div>
            @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="text-sm font-semibold text-forest-700 underline">{{ $s->cta_label }} &rarr;</a>@endif
        </div>
        @if ($featured->isEmpty())
            <p class="card mt-5 p-6 text-sm text-muted">Featured scholarships appear here once our editors have verified and published them. <a class="text-forest-700 underline" href="{{ route('scholarships.index') }}">Browse the directory</a>.</p>
        @else
            <div class="mt-5 grid gap-5 md:grid-cols-2 lg:grid-cols-3">@foreach ($featured as $c)<x-cycle-card :cycle="$c" />@endforeach</div>
        @endif
    </section>
    @break

@case('closing_soon')
    <section class="container-page py-6" aria-labelledby="closing-soon">
        <div class="rounded-xl border border-alert/30 bg-alert-soft p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="closing-soon" class="text-xl font-extrabold text-alert">{{ $s->heading }}</h2>
                @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="text-sm font-semibold text-alert underline">{{ $s->cta_label }} &rarr;</a>@endif
            </div>
            @if ($s->body)<p class="mt-1 text-sm text-ink/80">{{ $s->body }}</p>@endif
            @if ($closingSoon->isEmpty())
                <p class="mt-4 text-sm text-muted">No verified deadlines are closing in the next {{ config('sas.deadlines.closing_soon_days') }} days.</p>
            @else
                <div class="mt-4 grid gap-4 md:grid-cols-3">@foreach ($closingSoon as $c)<x-cycle-card :cycle="$c" />@endforeach</div>
            @endif
        </div>
    </section>
    @break

@case('destinations')
    <section class="container-page py-10" aria-labelledby="destinations">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 id="destinations" class="section-title">{{ $s->heading }}</h2>
                @if ($s->subheading)<p class="mt-1 text-muted">{{ $s->subheading }}</p>@endif</div>
            @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="text-sm font-semibold text-forest-700 underline">{{ $s->cta_label }} &rarr;</a>@endif
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
    @break

@case('services')
    <section class="bg-forest-50/60 py-12" aria-labelledby="services">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><h2 id="services" class="section-title">{{ $s->heading }}</h2>@if ($s->subheading)<p class="mt-1 text-muted">{{ $s->subheading }}</p>@endif</div>
                @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="text-sm font-semibold text-forest-700 underline">{{ $s->cta_label }} &rarr;</a>@endif
            </div>
            @if ($services->isEmpty())
                <p class="card mt-5 p-6 text-sm text-muted">Service pages will appear here once published. You can still <a class="text-forest-700 underline" href="{{ route('contact') }}">send us an enquiry</a>.</p>
            @else
                <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($services as $svc)
                        <li><a href="{{ route('services.show', $svc) }}" class="card block h-full p-5 hover:border-forest-500"><h3 class="font-bold">{{ $svc->name }}</h3><p class="mt-1 text-sm text-muted">{{ $svc->summary }}</p></a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
    @break

@case('how')
    <section class="container-page py-12" aria-labelledby="how">
        <h2 id="how" class="section-title">{{ $s->heading }}</h2>
        <ol class="mt-6 grid gap-5 md:grid-cols-3">
            @foreach (($s->items ?? []) as $i => $step)
                <li class="card p-6"><span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-lime font-bold text-forest-900" aria-hidden="true">{{ $i + 1 }}</span><h3 class="mt-3 text-lg font-bold">{{ $step['title'] ?? '' }}</h3><p class="mt-1 text-sm text-muted">{{ $step['text'] ?? '' }}</p></li>
            @endforeach
        </ol>
    </section>
    @break

@case('resources')
    <section class="container-page pb-10" aria-labelledby="resources">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="resources" class="section-title">{{ $s->heading }}</h2>
            @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="text-sm font-semibold text-forest-700 underline">{{ $s->cta_label }} &rarr;</a>@endif
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
    @break

@case('consultation')
    <section class="container-page pb-4">
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-forest-800 p-8 text-white">
            <div><h2 class="text-2xl font-extrabold !text-white">{{ $s->heading }}</h2>@if ($s->body)<p class="mt-1 text-forest-100">{{ $s->body }}</p>@endif</div>
            @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="btn-lime btn-lg">{{ $s->cta_label }} <span aria-hidden="true">&rarr;</span></a>@endif
        </div>
    </section>
    @break

@case('custom')
    <section class="container-page py-10" aria-labelledby="sec-{{ $s->id }}">
        <div class="grid items-center gap-8 {{ $s->image_path ? 'lg:grid-cols-2' : '' }}">
            <div>
                @if ($s->eyebrow)<p class="eyebrow">{{ $s->eyebrow }}</p>@endif
                <h2 id="sec-{{ $s->id }}" class="section-title mt-1">{{ $s->heading }}</h2>
                @if ($s->subheading)<p class="mt-1 text-muted">{{ $s->subheading }}</p>@endif
                @if ($s->body)<p class="mt-3 whitespace-pre-line">{{ $s->body }}</p>@endif
                @if ($s->cta_label || $s->cta2_label)
                <div class="mt-5 flex flex-wrap gap-3">
                    @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="btn-primary">{{ $s->cta_label }}</a>@endif
                    @if ($s->cta2_label)<a href="{{ $href($s->cta2_url) }}" class="btn-outline">{{ $s->cta2_label }}</a>@endif
                </div>
                @endif
            </div>
            @if ($s->image_path)<img src="{{ $media($s->image_path) }}" alt="" class="w-full rounded-2xl object-cover" loading="lazy">@endif
        </div>
        @if (! empty($s->items))
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($s->items as $item)
                    <li class="card p-5"><h3 class="font-bold">{{ $item['title'] ?? '' }}</h3><p class="mt-1 text-sm text-muted">{{ $item['text'] ?? '' }}</p></li>
                @endforeach
            </ul>
        @endif
    </section>
    @break

@endswitch
@endforeach
</x-layouts.public>
