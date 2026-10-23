<div style="font-size:.875rem;line-height:1.45">
    <dl style="display:grid;grid-template-columns:minmax(9rem,12rem) 1fr;gap:.25rem .75rem;margin:0">
        <dt style="color:#6b7280">Scholarship</dt><dd style="margin:0">{{ $plan->cycle->displayName() }}</dd>
        <dt style="color:#6b7280">Degree</dt><dd style="margin:0">{{ $plan->degreeLevel?->name ?? 'Not chosen' }}</dd>
        <dt style="color:#6b7280">Language of study</dt><dd style="margin:0">{{ $plan->study_language ?? 'Not chosen' }}</dd>
        <dt style="color:#6b7280">Sent by</dt><dd style="margin:0">{{ $plan->user->managedBy ? $plan->user->managedBy->name.' (agent)' : 'the student' }}</dd>
        <dt style="color:#6b7280">Status</dt><dd style="margin:0">{{ $plan->stageLabel() }}@if ($plan->review_remarks) : {{ $plan->review_remarks }}@endif</dd>
    </dl>
    <p style="margin:.75rem 0"><a href="{{ route('review.preview', $plan->public_id) }}" target="_blank" rel="noopener" style="text-decoration:underline">Open the application form (printable)</a></p>

    <h3 style="margin:1rem 0 .25rem;font-weight:600">Documents</h3>
    <p style="color:#6b7280;margin:0 0 .5rem">{{ $progress['ready'] }} of {{ $progress['required'] }} mandatory documents provided@if ($progress['excused']), {{ $progress['excused'] }} excused with a reason @endif.</p>
    <table style="width:100%;border-collapse:collapse">
        <tbody>
        @foreach ($rows as $r)
            @continue($r['documents']->isEmpty() && ! $r['reason'] && $r['requirement'] !== 'required')
            <tr style="border-top:1px solid #e5e7eb;vertical-align:top">
                <td style="padding:.35rem .5rem .35rem 0">{{ $r['kind']->name }} <span style="color:#6b7280">({{ $r['requirement'] === 'required' ? 'mandatory' : 'optional' }})</span></td>
                <td style="padding:.35rem 0">
                    @forelse ($r['documents'] as $d)
                        @if ($d->currentVersion?->isDownloadable())<a href="{{ route('files.download', $d) }}" target="_blank" rel="noopener" style="text-decoration:underline">{{ $d->currentVersion->original_name }}</a>@else {{ $d->currentVersion?->original_name ?? $d->title }} (not available) @endif<br>
                    @empty
                        @if ($r['reason'])<em>Cannot provide now: {{ $r['reason'] }}</em>@elseif ($r['requirement'] === 'required')<strong style="color:#b91c1c">Missing</strong>@endif
                    @endforelse
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
