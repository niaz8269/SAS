<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem">
            <p style="font-size:.9rem">
                @if ($total === 0) <strong>All clear.</strong> Nothing is unassigned, late or overdue.
                @else <strong>{{ $total }} {{ \Illuminate\Support\Str::plural('item', $total) }} need you</strong>@if ($late), <span style="color:#dc2626">{{ $late }} late</span>@endif: unassigned work, replies owed, reviews waiting and money overdue.
                @endif
            </p>
            <x-filament::button tag="a" href="{{ $url }}" color="{{ $late ? 'danger' : 'gray' }}">Open Needs attention</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>