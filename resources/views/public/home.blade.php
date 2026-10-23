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
    <section class="hero relative isolate overflow-hidden bg-navy-900">
        <img src="{{ $s->image_path ? $media($s->image_path) : asset('images/hero-graduates.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover object-center" width="1400" height="933" fetchpriority="high">
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-navy-900/80 via-navy-800/55 to-navy-900/85" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_center,rgba(202,162,73,0.18),transparent_60%)]" aria-hidden="true"></div>
        <div class="container-page hero-inner relative flex flex-col items-center text-center">
            @if ($s->eyebrow)<p class="inline-flex items-center gap-2 rounded-full border border-gold/50 bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-gold-soft backdrop-blur"><span class="h-1.5 w-1.5 rounded-full bg-gold" aria-hidden="true"></span>{{ $s->eyebrow }}</p>@endif
            <h1 class="hero-title mt-[clamp(0.75rem,2.5vh,1.5rem)] max-w-5xl font-extrabold text-white [text-shadow:0_2px_24px_rgba(10,34,80,0.55)]">{!! nl2br(e($s->heading)) !!}</h1>
            <div class="mt-[clamp(0.75rem,2.5vh,1.5rem)] h-1 w-24 rounded-full bg-gradient-to-r from-transparent via-gold to-transparent" aria-hidden="true"></div>
            @if ($s->body)<p class="hero-lead mt-[clamp(0.75rem,2.5vh,1.5rem)] max-w-2xl leading-relaxed text-white/85">{{ $s->body }}</p>@endif
            <div class="mt-[clamp(0.75rem,3vh,2rem)] flex flex-wrap justify-center gap-3">
                @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="btn-gold btn-lg rounded-lg shadow-lg shadow-black/20">{{ $s->cta_label }} <span aria-hidden="true">&rarr;</span></a>@endif
                @if ($s->cta2_label)<a href="{{ $href($s->cta2_url) }}" class="btn btn-lg rounded-lg border border-white/70 bg-white/10 text-white backdrop-blur transition hover:bg-white hover:text-navy-900">{{ $s->cta2_label }}</a>@endif
            </div>
            <form method="GET" action="{{ route('scholarships.index') }}" class="mt-[clamp(1rem,4vh,2.5rem)] w-full max-w-5xl rounded-2xl border border-white/30 bg-white/95 p-4 text-left shadow-2xl shadow-navy-900/30 backdrop-blur sm:p-5" role="search" aria-labelledby="hero-search">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 id="hero-search" class="text-base font-bold sm:text-lg">{{ $s->subheading ?: 'Search scholarships' }}</h2>
                    <p class="hidden text-sm text-muted sm:block">Find the right opportunities for your goals.</p>
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
    @if (! empty($s->items))
    <section class="container-page py-8" aria-label="How it works">
        <ul class="grid gap-5 sm:grid-cols-3">
            @foreach ($s->items as $item)
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-navy-700 text-sm font-bold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                    <span class="text-sm"><strong class="text-navy-900">{{ $item['title'] ?? '' }}</strong><br><span class="text-muted">{{ $item['text'] ?? '' }}</span></span>
                </li>
            @endforeach
        </ul>
    </section>
    @endif    @break

@case('degrees')
    @if ($degrees->isNotEmpty())
    <section class="container-page py-10" aria-labelledby="by-degree">
        <h2 id="by-degree" class="section-title">{{ $s->heading }}</h2>
        <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($degrees as $d)
                <li><a href="{{ route('scholarships.index', ['degree' => [$d->slug]]) }}" class="card flex items-center justify-between px-5 py-4 font-semibold hover:border-navy-500">{{ $d->name }} <span aria-hidden="true" class="text-navy-700">&rarr;</span></a></li>
            @endforeach
        </ul>
    </section>
    @endif
    @break

@case('featured')
    <section class="container-page py-10" aria-labelledby="featured">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>@if ($s->eyebrow)<p class="eyebrow">{{ $s->eyebrow }}</p>@endif<h2 id="featured" class="section-title mt-1">{{ $s->heading }}</h2></div>
            @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="text-sm font-semibold text-navy-700 underline">{{ $s->cta_label }} &rarr;</a>@endif
        </div>
        @if ($featured->isEmpty())
            <p class="card mt-5 p-6 text-sm text-muted">Featured scholarships appear here once our editors have verified and published them. <a class="text-navy-700 underline" href="{{ route('scholarships.index') }}">Browse the directory</a>.</p>
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
            @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="text-sm font-semibold text-navy-700 underline">{{ $s->cta_label }} &rarr;</a>@endif
        </div>
        @if ($destinations->isEmpty())
            <p class="card mt-5 p-6 text-sm text-muted">Destination guides will appear here once reviewed and published.</p>
        @else
            <ul class="mt-5 grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ($destinations as $c)
                    <li><x-destination-card :country="$c" size="sm" /></li>
                @endforeach
            </ul>
        @endif
    </section>
    @break

@case('services')
    <section class="bg-navy-50/60 py-12" aria-labelledby="services">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><h2 id="services" class="section-title">{{ $s->heading }}</h2>@if ($s->subheading)<p class="mt-1 text-muted">{{ $s->subheading }}</p>@endif</div>
                @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="text-sm font-semibold text-navy-700 underline">{{ $s->cta_label }} &rarr;</a>@endif
            </div>
            @if ($services->isEmpty())
                <p class="card mt-5 p-6 text-sm text-muted">Service pages will appear here once published. You can still <a class="text-navy-700 underline" href="{{ route('contact') }}">send us an enquiry</a>.</p>
            @else
                <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($services as $svc)
                        <li><a href="{{ route('services.show', $svc) }}" class="card block h-full p-5 hover:border-navy-500"><h3 class="font-bold">{{ $svc->name }}</h3><p class="mt-1 text-sm text-muted">{{ $svc->summary }}</p></a></li>
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
                <li class="card p-6"><span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-gold font-bold text-navy-900" aria-hidden="true">{{ $i + 1 }}</span><h3 class="mt-3 text-lg font-bold">{{ $step['title'] ?? '' }}</h3><p class="mt-1 text-sm text-muted">{{ $step['text'] ?? '' }}</p></li>
            @endforeach
        </ol>
    </section>
    @break

@case('resources')
    <section class="container-page pb-10" aria-labelledby="resources">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="resources" class="section-title">{{ $s->heading }}</h2>
            @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="text-sm font-semibold text-navy-700 underline">{{ $s->cta_label }} &rarr;</a>@endif
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
        <div class="flex flex-wrap items-center justify-between gap-6 rounded-2xl border border-gold/60 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex items-center gap-5">
                <img src="{{ asset('images/sas-seal.png') }}" alt="" class="hidden h-20 w-20 shrink-0 sm:block" width="80" height="80" loading="lazy">
                <div><h2 class="text-2xl font-extrabold">{{ $s->heading }}</h2>@if ($s->body)<p class="mt-1 text-muted">{{ $s->body }}</p>@endif</div>
            </div>
            @if ($s->cta_label)<a href="{{ $href($s->cta_url) }}" class="btn-gold btn-lg">{{ $s->cta_label }} <span aria-hidden="true">&rarr;</span></a>@endif
        </div>
    </section>
    @break

@case('trust')
    @if ($trustItems->isNotEmpty())
    @php $points = $trustItems->where('kind', 'point'); $partners = $trustItems->where('kind', 'partner'); @endphp
    <section class="bg-navy-50/60 py-12" aria-labelledby="trust">
        <div class="container-page">
            <h2 id="trust" class="section-title">{{ $s->heading }}</h2>
            @if ($s->subheading)<p class="mt-1 text-muted">{{ $s->subheading }}</p>@endif
            @if ($points->isNotEmpty())
                <ul class="mt-6 grid gap-4 md:grid-cols-3">
                    @foreach ($points as $t)
                        <li class="card p-5"><h3 class="font-bold">@if ($t->url)<a class="hover:underline" href="{{ $href($t->url) }}">{{ $t->title }}</a>@else{{ $t->title }}@endif</h3>@if ($t->text)<p class="mt-1 text-sm text-muted">{{ $t->text }}</p>@endif</li>
                    @endforeach
                </ul>
            @endif
            @if ($partners->isNotEmpty())
                <ul class="mt-8 flex flex-wrap items-center gap-x-8 gap-y-4" aria-label="Partners and accreditations">
                    @foreach ($partners as $t)
                        <li>
                            @php $logo = $t->image_path ? $media($t->image_path) : null; @endphp
                            @if ($t->url)<a href="{{ $href($t->url) }}" rel="noopener" class="inline-flex items-center gap-2">@endif
                            @if ($logo)<img src="{{ $logo }}" alt="{{ $t->title }}" class="h-10 w-auto max-w-[9rem] object-contain" loading="lazy">@else<span class="font-semibold">{{ $t->title }}</span>@endif
                            @if ($t->url)</a>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
    @endif
    @break

@case('testimonials')
    @if ($testimonials->isNotEmpty())
    <section class="container-page py-12" aria-labelledby="testimonials">
        <h2 id="testimonials" class="section-title">{{ $s->heading }}</h2>
        @if ($s->subheading)<p class="mt-1 text-muted">{{ $s->subheading }}</p>@endif
        <ul class="mt-6 grid gap-5 md:grid-cols-3">
            @foreach ($testimonials as $t)
                <li class="card flex h-full flex-col p-6">
                    <blockquote class="flex-1 text-sm">&ldquo;{{ $t->quote }}&rdquo;</blockquote>
                    <footer class="mt-4 flex items-center gap-3">
                        @if ($t->photo_path)<img src="{{ $media($t->photo_path) }}" alt="" class="h-10 w-10 rounded-full object-cover" loading="lazy">@endif
                        <span><span class="block font-semibold">{{ $t->name }}</span>@if ($t->context)<span class="block text-xs text-muted">{{ $t->context }}</span>@endif</span>
                    </footer>
                </li>
            @endforeach
        </ul>
    </section>
    @endif
    @break
@case('custom')
    <x-content-block :block="$s" />
    @break

@endswitch
@endforeach
</x-layouts.public>
