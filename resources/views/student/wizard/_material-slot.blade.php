{{-- One numbered document slot: what is asked, the files uploaded (several allowed), and "I cannot provide it now" with a reason. Expects $r, $n, $plan, $locked, $ctx. --}}
<li class="rounded-lg border border-line bg-white p-4">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <p class="font-medium"><span class="text-muted">{{ $n }}.</span> @if ($r['requirement'] === 'required')<span class="text-alert" title="Mandatory" aria-hidden="true">*</span>@endif {{ $r['kind']->name }}
                <span class="{{ $r['requirement'] === 'required' ? 'badge-warn' : 'badge-neutral' }}">{{ $r['requirement'] === 'required' ? 'Mandatory' : 'Optional' }}</span></p>
            @if ($r['note'])<p class="mt-0.5 text-sm text-muted">{{ $r['note'] }}</p>@elseif ($r['kind']->description)<p class="mt-0.5 text-sm text-muted">{{ $r['kind']->description }}</p>@endif
        </div>
        <span class="{{ match (true) { $r['state'] === 'ready' => 'badge-green', $r['state'] === 'scanning' => 'badge-warn', (bool) $r['reason'] => 'badge-neutral', default => 'badge-neutral' } }}">{{ match (true) { $r['state'] === 'ready' => '✓ Uploaded', $r['state'] === 'scanning' => 'Being checked', (bool) $r['reason'] => 'Cannot provide now', default => 'Not uploaded yet' } }}</span>
    </div>

    @if ($r['documents']->isNotEmpty())
        <ul class="mt-2 space-y-1">
            @foreach ($r['documents'] as $d)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-ivory-deep px-3 py-1.5 text-sm">
                    <span class="min-w-0 truncate">📄 {{ $d->currentVersion?->original_name ?? $d->title }}
                        @if ($d->currentVersion?->scan_status === \App\Enums\ScanStatus::Rejected)<span class="badge-alert">Rejected by the virus check</span>@elseif ($d->currentVersion?->scan_status === \App\Enums\ScanStatus::Pending)<span class="badge-warn">Being checked</span>@endif</span>
                    <span class="flex items-center gap-3">
                        @if ($d->currentVersion?->isDownloadable())<a class="text-xs text-navy-700 underline" href="{{ $ctx->route('documents.download', $d) }}">Download</a>@endif
                        @unless ($locked)<form method="POST" action="{{ $ctx->route('documents.destroy', $d) }}" onsubmit="return confirm('Remove this file?')">@csrf @method('DELETE')<input type="hidden" name="back" value="1"><input type="hidden" name="confirm" value="1"><button class="text-xs text-alert underline" aria-label="Remove {{ $d->currentVersion?->original_name ?? $d->title }}">Remove</button></form>@endunless
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    @unless ($locked)
        <form method="POST" action="{{ $ctx->route('documents.store') }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-end gap-2">
            @csrf
            <input type="hidden" name="type" value="{{ $r['kind']->key }}"><input type="hidden" name="acknowledge" value="1">
            <div><label for="up-{{ $r['kind']->key }}" class="sr-only">Upload files for {{ $r['kind']->name }}</label><input id="up-{{ $r['kind']->key }}" type="file" name="files[]" multiple required accept=".pdf,.docx,.jpg,.jpeg,.png" class="input !py-1.5 text-xs"></div>
            <button class="btn-primary !py-1.5">Upload</button>
        </form>

        @if ($r['requirement'] === 'required')
            <form method="POST" action="{{ $ctx->route('applications.reasons', $plan) }}" class="mt-3 border-t border-line pt-3" x-data="{ cannot: {{ $r['reason'] || old('cannot.'.$r['kind']->id) ? 'true' : 'false' }} }">
                @csrf @method('PUT')
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="cannot[{{ $r['kind']->id }}]" value="1" x-model="cannot" class="mt-0.5 rounded border-slate-300 text-navy-700"> <span>Currently cannot provide this document. Enter the reason.</span></label>
                <div x-show="cannot" x-cloak class="mt-2">
                    <label for="why-{{ $r['kind']->id }}" class="sr-only">Reason you cannot provide {{ $r['kind']->name }}</label>
                    <textarea id="why-{{ $r['kind']->id }}" name="reason[{{ $r['kind']->id }}]" rows="2" maxlength="500" class="input @error('reason.'.$r['kind']->id) input-error @enderror">{{ old('reason.'.$r['kind']->id, $r['reason']) }}</textarea>
                    @error('reason.'.$r['kind']->id)<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    <button class="btn-outline mt-2 !py-1.5">Save reason</button>
                </div>
                <noscript><button class="btn-outline mt-2 !py-1.5">Save</button></noscript>
            </form>
        @endif
    @else
        @if ($r['reason'])<p class="mt-2 text-sm text-muted">Cannot provide now: {{ $r['reason'] }}</p>@endif
    @endunless
</li>
