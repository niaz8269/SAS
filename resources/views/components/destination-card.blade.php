@props(['country', 'size' => 'md'])
{{-- Light image card: picture on top, name and a short line below. No dark gradient. --}}
<a href="{{ route('destinations.show', $country) }}" class="group card block h-full overflow-hidden transition hover:-translate-y-0.5 hover:border-navy-500 hover:shadow-md">
    <img src="{{ $country->imageUrl() }}" alt="" class="{{ $size === 'sm' ? 'aspect-[3/2]' : 'aspect-[16/10]' }} w-full object-cover" loading="lazy" width="400" height="250">
    <span class="block p-4">
        <span class="block text-lg font-bold text-navy-900 group-hover:underline">{{ $country->name }} <span aria-hidden="true" class="text-gold">&rarr;</span></span>
        @if ($country->summary && $size !== 'sm')<span class="mt-1 block text-sm text-muted">{{ \Illuminate\Support\Str::limit($country->summary, 80) }}</span>@endif
    </span>
</a>