@props(['compact' => false, 'light' => false])
@php $logo = \App\Domain\Site\Site::get('logo_path'); @endphp
<span {{ $attributes->class(['inline-flex items-center gap-2']) }}>
    @if ($logo)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public_media')->url($logo) }}" alt="" class="h-8 w-auto max-w-[9rem] shrink-0 object-contain">
    @else
        <svg viewBox="0 0 32 32" class="h-8 w-8 shrink-0 {{ $light ? 'text-lime' : 'text-forest-700' }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M16 27V13"/><path d="M16 13c0-4 3-7 8-7 0 5-3 8-8 8Z"/><path d="M16 17c0-3-2-5-6-5 0 4 2 6 6 6Z"/><path d="M6 27h20"/>
        </svg>
    @endif
    <span class="leading-tight">
        <span class="block text-xl font-extrabold tracking-tight {{ $light ? 'text-white' : 'text-forest-900' }}">{{ config('sas.brand.short') }}</span>
        @unless($compact)
            <span class="block text-[11px] font-medium {{ $light ? 'text-forest-100' : 'text-muted' }}">{{ \Illuminate\Support\Str::after(config('sas.brand.name'), '– ') }}</span>
        @endunless
    </span>
</span>
