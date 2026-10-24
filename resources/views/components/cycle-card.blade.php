@props(['cycle'])
@php
    $programme = $cycle->programme;
    $url = route('scholarships.show', [$programme->slug, $cycle->slug]);
    $institutions = $cycle->universities->isNotEmpty() ? $cycle->universities : $programme->universities;
@endphp
<article class="card flex h-full flex-col overflow-hidden transition hover:shadow-md">
    <a href="{{ $url }}" tabindex="-1" aria-hidden="true"><img src="{{ $programme->imageUrl($cycle) }}" alt="" class="aspect-video w-full object-cover" loading="lazy" width="960" height="540"></a>
    <div class="flex flex-1 flex-col p-5">
    <div class="flex flex-wrap items-center gap-2">
        @if ($cycle->sponsored)<span class="badge-warn">{{ $cycle->sponsored_label ?: 'Sponsored' }}</span>@endif
        @if ($cycle->featured && ! $cycle->sponsored)<span class="badge-gold">Featured</span>@endif
        @if ($cycle->needsRecheck())<span class="badge-warn">Needs recheck</span>@endif
    </div>
    <h3 class="mt-2 text-lg font-bold leading-snug"><a href="{{ $url }}" class="hover:underline">{{ $programme->title }}</a></h3>
    <p class="mt-1 text-sm text-muted">
        {{ $programme->funder?->name }}@if ($institutions->isNotEmpty() && $programme->funder) · @endif{{ $institutions->take(2)->pluck('name')->implode(', ') }}{{ $institutions->count() > 2 ? ' +'.($institutions->count() - 2) : '' }}
    </p>
    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
        <div><dt class="sr-only">Destination</dt><dd>{{ $cycle->destinations->pluck('name')->implode(', ') ?: 'Destination not confirmed' }}</dd></div>
        <div><dt class="sr-only">Degree</dt><dd>{{ $cycle->degreeLevels->pluck('name')->implode(', ') ?: 'Degree not confirmed' }}</dd></div>
        <div><dt class="sr-only">Funding</dt><dd class="font-medium text-navy-800">{{ $cycle->fundingLabel() }}</dd></div>
        <div><dt class="sr-only">Cycle</dt><dd>{{ $cycle->intake_year }} intake{{ $cycle->intake_term ? ' · '.$cycle->intake_term : '' }}</dd></div>
    </dl>
    <x-deadline :cycle="$cycle" class="mt-3" />
    <div class="mt-auto flex items-center justify-between gap-2 pt-4">
        <p class="text-xs text-muted">
            @if ($cycle->last_verified_at)Verified {{ $cycle->last_verified_at->format('j M Y') }}@else Not yet verified @endif
        </p>
        <a href="{{ $url }}" class="btn-outline !py-1.5" aria-label="View {{ $programme->title }} {{ $cycle->keyLabel() }}">View scholarship <span aria-hidden="true">&rarr;</span></a>
    </div>
    </div>
</article>