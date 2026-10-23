<div style="font-size:.875rem;line-height:1.4">
    <p>Degree level: <strong>{{ $case->degreeLevel?->name ?? 'not chosen yet' }}</strong>
        @if ($progress['required'] === 0) &middot; no mandatory documents
        @else &middot; <strong>{{ $progress['ready'] }} of {{ $progress['required'] }}</strong> mandatory documents provided @endif</p>
    @if ($progress['missing'])<p style="color:#b91c1c">Missing or not shared:{{ implode(', ', $progress['missing']) }}</p>@endif
    <table style="width:100%;margin-top:.75rem;border-collapse:collapse">
        <thead><tr style="text-align:left;color:#6b7280"><th style="padding:.25rem .5rem .25rem 0">Document</th><th style="padding:.25rem .5rem">Rule</th><th style="padding:.25rem 0 .25rem .5rem">Status</th></tr></thead>
        <tbody>
        @foreach ($rows as $r)
            <tr style="border-top:1px solid #e5e7eb;vertical-align:top">
                <td style="padding:.4rem .5rem .4rem 0">{{ $r['kind']->name }}@if ($r['note'])<br><span style="color:#6b7280">{{ $r['note'] }}</span>@endif</td>
                <td style="padding:.4rem .5rem">{{ $r['requirement'] === 'required' ? 'Mandatory' : 'Optional' }}</td>
                <td style="padding:.4rem 0 .4rem .5rem">{{ match ($r['state']) { 'ready' => 'Shared and ready', 'scanning' => 'Shared, being checked', default => 'Not shared with you' } }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p style="margin-top:.75rem;color:#6b7280">You see only documents the student has shared with this case. Open them under "Documents shared by the student".</p>
</div>
