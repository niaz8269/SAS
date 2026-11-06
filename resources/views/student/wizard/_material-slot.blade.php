{{-- One numbered document slot: what is asked, the files uploaded (several allowed), and "I cannot provide it now" with a reason. Expects $r, $n, $plan, $locked, $ctx. --}}
<li class="card p-4">
    <div class="grid gap-4 sm:grid-cols-[1fr_minmax(0,16rem)]">
        <div class="min-w-0">
            <p class="text-base font-bold text-ink">@if ($r['requirement'] === 'required')<span class="text-alert" title="Mandatory" aria-hidden="true">*</span> @endif{{ $n }}. {{ $r['kind']->name }}
                <span class="ml-1 text-sm font-normal {{ $r['requirement'] === 'required' ? 'text-alert' : 'text-muted' }}">({{ $r['requirement'] === 'required' ? 'mandatory' : 'optional' }})</span></p>
            @if ($r['note'])<p class="mt-1 text-sm text-muted">{{ $r['note'] }}</p>@elseif ($r['kind']->description)<p class="mt-1 text-sm text-muted">{{ $r['kind']->description }}</p>@endif
            @if ($r['kind']->template_path)<a href="{{ $ctx->route('documents.template', $r['kind']) }}" class="btn-outline mt-2 !px-3 !py-1.5 text-sm"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>Download blank form<span class="sr-only"> for {{ $r['kind']->name }}</span></a>@endif
            <p class="mt-2 text-sm"><span class="font-semibold {{ $r['state'] === 'ready' ? 'text-emerald-800' : 'text-muted' }}">{{ match (true) { $r['state'] === 'ready' => '✓ Uploaded', $r['state'] === 'scanning' => 'Being checked', (bool) $r['reason'] => 'Cannot provide now', default => 'Not uploaded yet' } }}</span></p>
        </div>

        <div class="min-w-0">
            @if ($r['documents']->isNotEmpty())
                <ul class="flex flex-wrap justify-end gap-3">
                    @foreach ($r['documents'] as $d)
                        @php($file = $d->currentVersion?->original_name ?? $d->title)
                        <li class="w-[6rem] text-center text-xs leading-tight text-muted">
                            @include('student.wizard._file-icon', ['name' => $file])
                            <span class="mx-auto block break-all" title="{{ $file }}">{{ \Illuminate\Support\Str::limit($file, 22) }}</span>
                            @if ($d->currentVersion?->quality_note)<span class="block text-alert" title="{{ $d->currentVersion->quality_note }}">Check: small file</span>@endif
                            @if ($d->currentVersion?->scan_status === \App\Enums\ScanStatus::Rejected)<span class="block text-alert">Rejected by the virus check</span>@elseif ($d->currentVersion?->scan_status === \App\Enums\ScanStatus::Pending)<span class="block text-ink">Being checked</span>@endif
                            <span class="mt-0.5 flex justify-center gap-2 text-sm">
                                @if ($d->currentVersion?->isDownloadable())<a class="text-navy-700 underline" href="{{ $ctx->route('documents.download', $d) }}">Download</a>@endif
                                @unless ($locked)<form method="POST" action="{{ $ctx->route('documents.destroy', $d) }}" onsubmit="return confirm('Remove this file?')">@csrf @method('DELETE')<input type="hidden" name="back" value="1"><input type="hidden" name="confirm" value="1"><button class="text-alert underline" aria-label="Remove {{ $file }}">Remove</button></form>@endunless
                            </span>
                        </li>
                    @endforeach
                </ul>
                @foreach ($r['documents'] as $d)
                    @php($note = $d->comments->sortByDesc('id')->first())
                    @if ($note)<p class="mt-2 rounded-lg border border-gold bg-gold/10 px-3 py-2 text-sm text-ink">Comment from {{ $note->author?->name ?: 'SAS' }} on {{ \Illuminate\Support\Str::limit($d->currentVersion?->original_name ?? $d->title, 24) }}: {{ $note->body }}</p>@endif
                @endforeach
            @endif
        </div>
    </div>

    @unless ($locked)
        <form method="POST" action="{{ $ctx->route('documents.store') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-3">
            @csrf
            <input type="hidden" name="type" value="{{ $r['kind']->key }}"><input type="hidden" name="acknowledge" value="1">
            <label for="up-{{ $r['kind']->key }}" class="sr-only">Upload files for {{ $r['kind']->name }}</label>
            <input id="up-{{ $r['kind']->key }}" type="file" name="files[]" multiple required accept=".pdf,.docx,.jpg,.jpeg,.png" class="max-w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm">
            <label class="flex items-center gap-2 text-sm text-muted"><input type="checkbox" name="combine" value="1" class="h-4 w-4"> Combine several photos into one PDF</label>
            <button class="btn-primary !py-2 text-sm">Upload</button>
        </form>

        @if ($r['requirement'] === 'required')
            <form method="POST" action="{{ $ctx->route('applications.reasons', $plan) }}" class="mt-3 border-t border-line pt-3" x-data="{ cannot: {{ $r['reason'] || old('cannot.'.$r['kind']->id) ? 'true' : 'false' }} }">
                @csrf @method('PUT')
                <label class="flex items-start gap-2 text-sm text-muted"><input type="checkbox" name="cannot[{{ $r['kind']->id }}]" value="1" x-model="cannot" class="mt-0.5 h-4 w-4"> <span>Currently cannot provide the document, enter the reason:</span></label>
                <div x-show="cannot" x-cloak class="mt-2">
                    <label for="why-{{ $r['kind']->id }}" class="sr-only">Reason you cannot provide {{ $r['kind']->name }}</label>
                    <textarea id="why-{{ $r['kind']->id }}" name="reason[{{ $r['kind']->id }}]" rows="2" maxlength="500" class="w-full rounded-lg border bg-white px-3 py-2 text-base @error('reason.'.$r['kind']->id) border-alert @else border-slate-300 @enderror">{{ old('reason.'.$r['kind']->id, $r['reason']) }}</textarea>
                    @error('reason.'.$r['kind']->id)<p class="mt-1 text-sm text-alert" role="alert">{{ $message }}</p>@enderror
                    <button class="btn-outline mt-2 !py-2 text-sm">Save reason</button>
                </div>
                <noscript><button class="btn-outline mt-2 !py-2 text-sm">Save</button></noscript>
            </form>
        @endif
    @else
        @if ($r['reason'])<p class="mt-2 text-sm text-muted">Cannot provide now: {{ $r['reason'] }}</p>@endif
    @endunless
</li>
