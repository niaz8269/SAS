@props(['height' => '12rem'])
@php
    // The text is cut on the server when it is clearly longer than the limit (about 85 characters a line, 24 px a line), so the page does not jump when it loads.
    $rem = (float) $height;
    $cut = mb_strlen(trim(strip_tags((string) $slot))) > (int) round($rem * 16 / 24 * 85);
@endphp
{{-- Long text, shortened with a "Read more" button. The whole text is in the page (search engines and screen readers see all of it); it is only shortened on screen,
     and only when it is really taller than the limit, so short text is never cut. Without JavaScript nothing is shortened. --}}
<div x-data="{ open: false, long: {{ $cut ? 'true' : 'false' }} }" x-init="$nextTick(() => { long = $refs.body.scrollHeight > {{ (int) ($rem * 16) }} + 24 })" {{ $attributes }}>
    <div x-ref="body" class="relative overflow-hidden" style="{{ $cut ? 'max-height: '.$height : '' }}" :style="long && ! open ? 'max-height: {{ $height }}' : 'max-height: none'">
        {{ $slot }}
        <div x-show="long && ! open" x-cloak aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-white to-transparent"></div>
    </div>
    <button type="button" x-show="long" x-cloak @click="open = ! open" :aria-expanded="open" class="mt-2 text-sm font-semibold text-navy-700 underline underline-offset-2 hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-navy-700">
        <span x-text="open ? 'Show less' : 'Read more'"></span>
    </button>
</div>