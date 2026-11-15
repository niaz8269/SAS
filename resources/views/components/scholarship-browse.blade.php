@php
    $pages = app(\App\Domain\Seo\Landing::class)->pages();
    $groups = [
        'Destination' => $pages->where('type', 'in')->sortByDesc('count')->take(8),
        'Degree' => $pages->where('type', 'for')->sortByDesc('count'),
        'Funding' => $pages->where('type', 'funding')->sortByDesc('count'),
    ];
    $total = \App\Models\ScholarshipCycle::visible()->count();
@endphp
@if ($pages->isNotEmpty())
    <section class="container-page pb-10" aria-labelledby="browse-sch-h">
        <h2 id="browse-sch-h" class="section-title">Browse {{ number_format($total) }} scholarships</h2>
        <p class="mt-2 max-w-3xl text-sm text-muted">Every listing shows its deadline, funding, eligibility, documents and the source it was read from. Start from a destination, a degree level or the kind of funding.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            @foreach ($groups as $name => $set)
                @if ($set->isNotEmpty())
                    <div class="card p-4"><h3 class="text-sm font-bold">By {{ strtolower($name) }}</h3>
                        <ul class="mt-2 flex flex-wrap gap-2 text-sm">@foreach ($set as $p)<li><a class="badge-neutral hover:underline" href="{{ $p['url'] }}">{{ $p['label'] }} <span class="opacity-70">{{ $p['count'] }}</span></a></li>@endforeach</ul></div>
                @endif
            @endforeach
        </div>
    </section>
@endif
