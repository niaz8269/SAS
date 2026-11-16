<x-filament-widgets::widget>
    <x-filament::section heading="Quick links" compact>
        <x-slot name="afterHeader">
            <x-filament::button tag="a" href="{{ $siteUrl }}" target="_blank" rel="noopener" color="gray" size="sm" icon="heroicon-o-arrow-top-right-on-square">View website</x-filament::button>
        </x-slot>
        {{-- Inline styles: the admin ships Filament's own stylesheet, not the public site's Tailwind classes. --}}
        <div style="display:flex;flex-wrap:wrap;gap:.5rem">
            @foreach ($links as [$label, $hint, $url])
                <a href="{{ $url }}" title="{{ $hint }}" style="display:inline-block;border:1px solid rgba(128,128,128,.35);border-radius:999px;padding:.35rem .85rem;font-size:.8rem;font-weight:500;text-decoration:none">{{ $label }}</a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
