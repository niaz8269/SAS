<x-layouts.public :title="'Study in '.$country->name" :description="\Illuminate\Support\Str::limit(strip_tags($country->summary ?? 'Study guidance and scholarships in '.$country->name), 155)">
    <section class="border-b border-line bg-white"><div class="container-page py-8">
        <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('destinations.index') }}">Study destinations</a> / <span aria-current="page">{{ $country->name }}</span></nav>
        <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">Study in {{ $country->name }}</h1>
        @if ($country->summary)<p class="mt-3 max-w-3xl text-lg text-muted">{{ $country->summary }}</p>@endif
        <figure class="mt-4 max-w-xl"><img src="{{ $country->imageUrl() }}" alt="" class="aspect-video w-full rounded-xl border border-line object-cover" width="960" height="540"></figure>
        <p class="mt-3 text-sm text-muted">
            @if ($country->guidance_reviewed_at) Last reviewed {{ $country->guidance_reviewed_at->format('j F Y') }}. @else Review date not recorded. @endif
            Check official sources before you apply or travel.
        </p>
    </div></section>

    <div class="container-page grid gap-8 py-8 lg:grid-cols-[1fr_20rem]">
        <div>
            @if ($country->study_guidance)<div class="prose-sas">{!! \App\Support\Html::clean($country->study_guidance) !!}</div>@endif

            @if (! empty($country->cost_notes))
                <section class="card mt-6 p-6" aria-labelledby="costs"><h2 id="costs" class="text-xl font-bold">Typical costs</h2>
                    <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Costs in {{ $country->name }}</caption>
                        <thead><tr class="border-b border-line text-muted"><th scope="col" class="py-2 pr-3">Item</th><th scope="col" class="py-2 pr-3">Amount</th><th scope="col" class="py-2">Updated</th></tr></thead>
                        <tbody>@foreach ($country->cost_notes as $row)<tr class="border-b border-line/60"><td class="py-2 pr-3">{{ $row['label'] ?? '' }}</td><td class="py-2 pr-3">{{ $row['amount_text'] ?? '' }} {{ $row['currency'] ?? '' }}</td><td class="py-2">{{ $row['updated_on'] ?? 'Not recorded' }}</td></tr>@endforeach</tbody></table></div>
                </section>
            @endif

            @if (! empty($country->guidance_sources))
                <section class="mt-6" aria-labelledby="sources"><h2 id="sources" class="text-lg font-bold">Sources</h2>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach ($country->guidance_sources as $s)<li><a class="text-navy-700 underline" rel="noopener nofollow" target="_blank" href="{{ $s['url'] }}">{{ $s['label'] ?? $s['url'] }}</a></li>@endforeach</ul></section>
            @endif

            <section class="mt-8" aria-labelledby="dest-scholarships"><div class="flex flex-wrap items-end justify-between gap-2"><h2 id="dest-scholarships" class="section-title">Scholarships in {{ $country->name }}</h2>
                <a class="text-sm font-semibold text-navy-700 underline" href="{{ route('scholarships.index', ['destination' => [$country->slug]]) }}">See all &rarr;</a></div>
                @if ($cycles->isEmpty())
                    <p class="card mt-4 p-6 text-sm text-muted">No published scholarships for {{ $country->name }} yet. <a class="text-navy-700 underline" href="{{ route('contact', ['category' => 'advisory']) }}">Ask us</a> and we will look into it.</p>
                @else
                    <div class="mt-4 grid gap-5 md:grid-cols-2">@foreach ($cycles as $c)<x-cycle-card :cycle="$c" />@endforeach</div>
                @endif
            </section>
        </div>
        <aside class="space-y-4" aria-label="Related services">
            <div class="card p-5"><h2 class="font-bold">Need help applying?</h2>
                <ul class="mt-3 space-y-2 text-sm">@foreach ($services as $s)<li><a class="text-navy-700 underline" href="{{ route('services.show', $s) }}">{{ $s->name }}</a></li>@endforeach</ul>
                <a href="{{ route('consultations.create') }}" class="btn-primary mt-4 w-full">Book a Consultation</a></div>
        </aside>
    </div>
</x-layouts.public>
