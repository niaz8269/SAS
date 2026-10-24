<x-filament-panels::page>
    <p style="font-size:.9rem;opacity:.8">
        @if ($total === 0) Nothing needs attention right now. @else {{ $total }} {{ \Illuminate\Support\Str::plural('item', $total) }} to look at, {{ $lateCount }} of them late. The limits are set in <code>.env</code> (SAS_SLA_*). @endif
    </p>
    @foreach ($groups as $key => $group)
        @continue($group['rows']->isEmpty())
        <x-filament::section :heading="$group['title'].' ('.$group['rows']->count().')'" :description="$group['hint']" collapsible compact>
            @foreach ($group['rows'] as $row)
                @php($l = $line($key, $row))
                <div style="display:grid;grid-template-columns:minmax(12rem,22rem) 1fr;gap:.75rem;padding:.35rem 0;font-size:.85rem;border-bottom:1px solid rgba(128,128,128,.2)">
                    <span style="font-weight:600">@if ($l['url'])<a href="{{ $l['url'] }}" style="text-decoration:underline">{{ $l['label'] }}</a>@else {{ $l['label'] }} @endif</span>
                    <span style="opacity:.8">{{ $l['detail'] }}</span>
                </div>
            @endforeach
        </x-filament::section>
    @endforeach
</x-filament-panels::page>