<x-filament-panels::page>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <thead>
                <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.4)">
                    <th style="padding:.5rem">Counsellor</th><th style="padding:.5rem">Students</th><th style="padding:.5rem">Waiting for reply</th><th style="padding:.5rem">Follow-ups due</th><th style="padding:.5rem">Gone quiet</th>
                    <th style="padding:.5rem">Average reply time</th><th style="padding:.5rem">Applications</th><th style="padding:.5rem">Sent or passed</th><th style="padding:.5rem">Offers</th><th style="padding:.5rem">Students by status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr style="border-bottom:1px solid rgba(128,128,128,.25);vertical-align:top">
                        <td style="padding:.5rem;font-weight:600">{{ $r['counsellor']->name }}</td>
                        <td style="padding:.5rem">{{ $r['students'] }}</td>
                        <td style="padding:.5rem">{{ $r['waiting'] }}</td>
                        <td style="padding:.5rem">{{ $r['due'] }}</td>
                        <td style="padding:.5rem">{{ $r['quiet'] }}</td>
                        <td style="padding:.5rem">{{ $r['reply_hours'] === null ? '-' : $r['reply_hours'].' h' }}</td>
                        <td style="padding:.5rem">{{ $r['applications'] }}</td>
                        <td style="padding:.5rem">{{ $r['submitted'] }} ({{ $r['passed'] }} passed)</td>
                        <td style="padding:.5rem">{{ $r['offers'] }}</td>
                        <td style="padding:.5rem">@foreach ($r['by_status'] as $k => $n)<span style="display:block">{{ $labels[$k] ?? $k }}: {{ $n }}</span>@endforeach @if (! $r['by_status']) - @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="padding:1rem;opacity:.7">No counsellors yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>