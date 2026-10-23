{{-- One document in a checklist: what is needed, the files uploaded for it, and an input that takes any number of files. Expects $r from App\Domain\Documents\Requirements. --}}
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
    @if ($r['documents']->isNotEmpty())
        <ul class="mt-2 space-y-1">
            @foreach ($r['documents'] as $d)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-ivory-deep px-3 py-1.5 text-sm">
                    <span class="min-w-0 truncate">{{ $d->currentVersion?->original_name ?? $d->title }}
                        @if ($d->currentVersion?->scan_status === \App\Enums\ScanStatus::Rejected)<span class="badge-alert">Rejected by the virus check</span>@elseif ($d->currentVersion?->scan_status === \App\Enums\ScanStatus::Pending)<span class="badge-warn">Being checked</span>@endif</span>
                    <form method="POST" action="{{ route('student.documents.destroy', $d) }}" onsubmit="return confirm('Remove this file?')">@csrf @method('DELETE')<input type="hidden" name="back" value="1"><input type="hidden" name="confirm" value="1"><button class="text-xs text-alert underline" aria-label="Remove {{ $d->currentVersion?->original_name ?? $d->title }}">Remove</button></form>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($r['documents']->isEmpty())
        @include('student._document-upload', ['r' => $r])
    @else
        <details class="mt-2">
            <summary class="cursor-pointer text-xs text-navy-700 underline">Add more files</summary>
            @include('student._document-upload', ['r' => $r])
        </details>
    @endif
</li>
