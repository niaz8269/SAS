<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;align-items:end;gap:1rem;font-size:.85rem">
        <label>From<br><input type="date" wire:model.live="from" style="border:1px solid rgba(128,128,128,.5);border-radius:.4rem;padding:.35rem .5rem;background:transparent"></label>
        <label>To<br><input type="date" wire:model.live="to" style="border:1px solid rgba(128,128,128,.5);border-radius:.4rem;padding:.35rem .5rem;background:transparent"></label>
        <span style="opacity:.7">Counts use the dates above. "Now" lines are today's position.</span>
    </div>
    @foreach ($sections as $key => $s)
        <x-filament::section :heading="$s['title']" collapsible compact>
            <x-slot name="afterHeader"><x-filament::button tag="a" size="sm" color="gray" href="{{ route('admin.reports.csv', $key) }}?{{ $query }}" icon="heroicon-o-arrow-down-tray">CSV</x-filament::button></x-slot>
            <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:.85rem">
                <thead><tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.4)">@foreach ($s['headers'] as $h)<th style="padding:.4rem .5rem">{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse ($s['rows'] as $row)
                    <tr style="border-bottom:1px solid rgba(128,128,128,.2)">@foreach ($row as $cell)<td style="padding:.4rem .5rem">{{ $cell }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($s['headers']) }}" style="padding:.75rem .5rem;opacity:.7">Nothing in this period.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </x-filament::section>
    @endforeach
</x-filament-panels::page>