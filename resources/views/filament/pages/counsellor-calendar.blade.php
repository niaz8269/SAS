<x-filament-panels::page>
    @forelse ($agenda as $date => $items)
        @php($d = \Illuminate\Support\Carbon::parse($date))
        <x-filament::section :heading="($d->isToday() ? 'Today, ' : ($d->isTomorrow() ? 'Tomorrow, ' : '')).$d->format('l j F')" compact>
            @foreach ($items as $i)
                <div style="display:grid;grid-template-columns:7.5rem 1fr;gap:.75rem;padding:.35rem 0;font-size:.875rem">
                    <span style="font-weight:600">{{ $i['kind'] }}@if ($i['time']) <span style="font-weight:400;opacity:.7">{{ $i['time'] }}</span>@endif</span>
                    <span>@if ($i['url'])<a href="{{ $i['url'] }}" style="text-decoration:underline">{{ $i['text'] }}</a>@else {{ $i['text'] }} @endif</span>
                </div>
            @endforeach
        </x-filament::section>
    @empty
        <p style="opacity:.7">Nothing is planned for the next 30 days. Set a follow-up date when you update a student's status and it shows up here.</p>
    @endforelse
</x-filament-panels::page>