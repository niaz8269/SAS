<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:.75rem;font-size:.85rem">
        @foreach ($checks as $c)
            @php($colour = ['ok' => '#16a34a', 'warn' => '#d97706', 'bad' => '#dc2626'][$c['level']])
            <div style="border:1px solid rgba(128,128,128,.35);border-left:5px solid {{ $colour }};border-radius:.6rem;padding:.8rem 1rem">
                <p style="font-weight:600">{{ $c['label'] }}</p>
                <p style="margin-top:.2rem">{{ $c['value'] }}</p>
                @if ($c['hint'])<p style="margin-top:.35rem;opacity:.7">{{ $c['hint'] }}</p>@endif
            </div>
        @endforeach
    </div>
    <x-filament::section heading="In the system" compact>
        <div style="display:flex;flex-wrap:wrap;gap:2rem;font-size:.9rem">@foreach ($counts as $label => $n)<div><span style="opacity:.7">{{ $label }}</span><br><strong>{{ $n }}</strong></div>@endforeach</div>
    </x-filament::section>
    <x-filament::section heading="Latest backups" description="Written by the daily job at 02:30 or by Back up now." compact>
        @forelse ($backups as $b)
            <p style="font-size:.85rem;padding:.2rem 0">{{ $b['name'] }} <span style="opacity:.7">- {{ $b['at']->format('j M Y H:i') }}, {{ round($b['size'] / 1048576, 1) }} MB</span></p>
        @empty
            <p style="opacity:.7;font-size:.85rem">No backup yet.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>