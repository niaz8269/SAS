<x-filament-panels::page>
    <p style="font-size:.9rem;opacity:.8">Load = students + open cases + open enquiries + twice the replies owed. @if ($least) <strong>{{ $least->name }}</strong> is the least busy right now. Use <em>Assign to the least busy</em> on the Students, Cases and Enquiries lists to hand out new work evenly. @endif</p>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <thead><tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.4)">
                <th style="padding:.5rem">Person</th><th style="padding:.5rem">Students</th><th style="padding:.5rem">Open cases</th><th style="padding:.5rem">Open enquiries</th><th style="padding:.5rem">Replies owed</th><th style="padding:.5rem;min-width:12rem">Load</th>
            </tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr style="border-bottom:1px solid rgba(128,128,128,.25)">
                    <td style="padding:.5rem;font-weight:600">{{ $r['counsellor']->name }}</td>
                    <td style="padding:.5rem">{{ $r['students'] }}</td>
                    <td style="padding:.5rem">{{ $r['cases'] }}</td>
                    <td style="padding:.5rem">{{ $r['enquiries'] }}</td>
                    <td style="padding:.5rem">{{ $r['replies'] }}</td>
                    <td style="padding:.5rem"><span style="display:inline-block;height:.6rem;border-radius:.3rem;background:#428bb6;width:{{ max(4, round($r['score'] / $max * 100)) }}%"></span> {{ $r['score'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:1rem;opacity:.7">No counsellors yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>