@props(['compact' => false, 'light' => false])
@php
    // The logo staff chose under Site settings wins; otherwise the bundled SAS logo (full lockup, or just the seal when compact).
    $custom = \App\Domain\Site\Site::get('logo_path');
    // Pre-scaled, lightly sharpened copies of the lockup: the browser's own shrinking of the 2000px file smudges the thin lettering round the seal.
    $srcset = (! $custom && ! $compact) ? asset('images/sas-logo-480.webp').' 480w, '.asset('images/sas-logo-720.webp').' 720w, '.asset('images/sas-logo-960.webp').' 960w, '.asset('images/sas-logo.webp').' 2000w' : null;
    $src = $custom ? \Illuminate\Support\Facades\Storage::disk('public_media')->url($custom) : ($compact ? asset('images/sas-seal.png') : asset('images/sas-logo.webp'));
@endphp
<span {{ $attributes->class(['inline-flex items-center']) }}>
    @if ($light)
        {{-- On the dark footer the navy artwork sits on a light plate so it stays legible. --}}
        <span class="inline-flex rounded-xl bg-ivory p-2"><img src="{{ $src }}" @if ($srcset) srcset="{{ $srcset }}" sizes="14rem" @endif alt="{{ config('sas.brand.name') }}" class="h-14 w-auto max-w-[14rem] object-contain" width="{{ $compact ? 56 : 158 }}" height="56"></span>
    @else
        <img src="{{ $src }}" @if ($srcset) srcset="{{ $srcset }}" sizes="16rem" @endif alt="" class="{{ $compact ? 'h-10' : 'h-12 sm:h-14' }} w-auto max-w-[13rem] shrink-0 object-contain" width="{{ $compact ? 40 : 158 }}" height="{{ $compact ? 40 : 56 }}">
    @endif
</span>