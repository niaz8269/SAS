<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Enquiry funnel</x-slot>
        @if ($total === 0)
            <p style="color:var(--sas-muted);font-size:.9rem">No enquiries yet. They appear here as soon as a visitor writes to SAS.</p>
        @else
            {{-- Inline styles: the admin ships Filament's own stylesheet, not the public site's Tailwind classes. --}}
            <div style="display:grid;gap:.8rem">
                @foreach ($rows as $r)
                    <div style="display:grid;grid-template-columns:7.5rem 1fr 2.5rem;align-items:center;gap:.75rem;font-size:.875rem">
                        <span style="color:var(--sas-muted)">{{ $r['label'] }}</span>
                        <span style="display:block;height:.7rem;border-radius:999px;background:#eef1f7;overflow:hidden"><span style="display:block;height:100%;width:{{ $r['n'] ? max(3, round($r['n'] / $max * 100)) : 0 }}%;background:{{ $r['closed'] ? '#9aa6bd' : 'var(--sas-navy)' }};border-radius:999px"></span></span>
                        <span style="font-weight:600;text-align:right">{{ number_format($r['n']) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
