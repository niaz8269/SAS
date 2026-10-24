<x-filament-panels::page>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <thead><tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.4)">
                <th style="padding:.5rem">Agency</th><th style="padding:.5rem">People</th><th style="padding:.5rem">Students</th><th style="padding:.5rem">Unfinished</th><th style="padding:.5rem">Waiting for review</th><th style="padding:.5rem">Oldest wait</th>
                <th style="padding:.5rem">Returned</th><th style="padding:.5rem">Passed</th><th style="padding:.5rem">Fees owed</th><th style="padding:.5rem">Last activity</th>
            </tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr style="border-bottom:1px solid rgba(128,128,128,.25)">
                    <td style="padding:.5rem;font-weight:600"><a href="{{ \App\Filament\Resources\PartnerOrganisations\PartnerOrganisationResource::getUrl('edit', ['record' => $r['org']]) }}" style="text-decoration:underline">{{ $r['org']->name }}</a>@if (! $r['org']->isActive()) <span style="opacity:.7">(suspended)</span>@endif</td>
                    <td style="padding:.5rem">{{ $r['people'] }}</td>
                    <td style="padding:.5rem">{{ $r['students'] }}</td>
                    <td style="padding:.5rem">{{ $r['draft'] }}</td>
                    <td style="padding:.5rem">{{ $r['submitted'] }}</td>
                    <td style="padding:.5rem">{{ $r['oldest_review_days'] === null ? '-' : $r['oldest_review_days'].' days' }}</td>
                    <td style="padding:.5rem">{{ $r['returned'] }}</td>
                    <td style="padding:.5rem">{{ $r['passed'] }}</td>
                    <td style="padding:.5rem">{{ $r['owed'] }} ({{ $r['owed_count'] }})</td>
                    <td style="padding:.5rem">{{ $r['last_activity'] ? $r['last_activity']->diffForHumans() : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" style="padding:1rem;opacity:.7">No recruiting agents yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>