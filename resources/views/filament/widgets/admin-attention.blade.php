<x-filament-widgets::widget>
    @php($accent = $total === 0 ? '#16a34a' : ($late ? '#dc2626' : '#caa249'))
    <x-filament::section compact>
        {{-- Inline styles: the admin ships Filament's own stylesheet, not the public site's Tailwind classes. --}}
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;border-left:4px solid {{ $accent }};padding-left:.9rem">
            <div>
                @if ($total === 0)
                    <p style="font-weight:600;font-size:.95rem">All clear</p>
                    <p style="margin-top:.15rem;font-size:.8rem;opacity:.7">Nothing is unassigned, late or overdue.</p>
                @else
                    <p style="font-weight:600;font-size:.95rem">{{ $total }} {{ \Illuminate\Support\Str::plural('item', $total) }} need you @if ($late)<span style="color:#dc2626;font-weight:500">&middot; {{ $late }} late</span>@endif</p>
                    <p style="margin-top:.15rem;font-size:.8rem;opacity:.7">Unassigned work, replies owed, reviews waiting and overdue payments.</p>
                @endif
            </div>
            <x-filament::button tag="a" href="{{ $url }}" color="{{ $late ? 'danger' : 'gray' }}" size="sm" outlined>Open Needs attention</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
