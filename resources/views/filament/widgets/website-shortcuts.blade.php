<x-filament-widgets::widget>
    <x-filament::section heading="Manage your website" description="Everything visitors see can be changed from here. Changes appear on the live site straight away.">
        <x-slot name="afterHeader">
            <x-filament::button tag="a" href="{{ $siteUrl }}" target="_blank" rel="noopener" color="gray" icon="heroicon-o-arrow-top-right-on-square">View website</x-filament::button>
        </x-slot>
        @unless ($canEditSite)
            <p style="margin-bottom:.75rem;padding:.75rem 1rem;border-radius:.75rem;background:rgba(245,158,11,.12);font-size:.85rem">
                You are signed in as <strong>{{ $roles ?: 'staff' }}</strong>. This role cannot edit the website itself (site settings, homepage, menus, pages, media, trust section, testimonials). Sign in with an Administrator or Super administrator account to see those screens.
            </p>
        @endunless
        {{-- Inline styles: the admin ships Filament's own stylesheet, not the public site's Tailwind classes. --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem">
            @foreach ($links as [$label, $hint, $url])
                <a href="{{ $url }}" style="display:block;border:1px solid rgba(128,128,128,.35);border-radius:.75rem;padding:1rem;text-decoration:none">
                    <span style="display:block;font-weight:600;font-size:.9rem">{{ $label }}</span>
                    <span style="display:block;margin-top:.25rem;font-size:.75rem;opacity:.7">{{ $hint }}</span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
