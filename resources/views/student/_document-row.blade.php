{{-- One document in a checklist: what is needed, its status, and how to upload it. Expects $r from App\Domain\Documents\Requirements. --}}
@php($uploaded = $r['state'] !== 'missing')
<li class="py-4">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <p class="font-medium">{{ $r['kind']->name }}
                <span class="{{ $r['requirement'] === 'required' ? 'badge-warn' : 'badge-neutral' }}">{{ $r['requirement'] === 'required' ? 'Mandatory' : 'Optional' }}</span></p>
            @if ($r['note'])<p class="mt-0.5 text-sm text-muted">{{ $r['note'] }}@if ($r['source'] && str_starts_with($r['source'], 'https://')) <a class="text-navy-700 underline" rel="noopener" href="{{ $r['source'] }}">Official source</a>@endif</p>
            @elseif ($r['kind']->description)<p class="mt-0.5 text-sm text-muted">{{ $r['kind']->description }}</p>@endif
        </div>
        <span class="{{ match ($r['state']) { 'ready' => 'badge-green', 'scanning' => 'badge-warn', default => 'badge-neutral' } }}">{{ match ($r['state']) { 'ready' => '✓ Uploaded', 'scanning' => 'Being checked', default => 'Not uploaded yet' } }}</span>
    </div>
    @foreach ($r['documents'] as $d)<p class="mt-1 text-xs text-muted">{{ $d->currentVersion?->original_name ?? $d->title }}</p>@endforeach
    @if ($uploaded)
        <details class="mt-2">
            <summary class="cursor-pointer text-xs text-navy-700 underline">Upload a different file</summary>
            @include('student._document-upload', ['r' => $r])
        </details>
    @else
        @include('student._document-upload', ['r' => $r])
    @endif
</li>
