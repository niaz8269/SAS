{{-- One numbered document slot: what is asked, the files uploaded (several allowed), and "I cannot provide it now" with a reason. Expects $r, $n, $plan, $locked, $ctx. --}}
<li class="border border-[#e0e0e0] bg-[#f2f2f2] p-4">
    <div class="grid gap-4 sm:grid-cols-[1fr_minmax(0,16rem)]">
        <div class="min-w-0">
            <p class="text-[14px] font-bold text-[#333]">@if ($r['requirement'] === 'required')<span class="text-[#d9534f]" title="Mandatory" aria-hidden="true">*</span> @endif{{ $n }}.{{ $r['kind']->name }}
                <span class="ml-1 text-xs font-normal {{ $r['requirement'] === 'required' ? 'text-[#b4530b]' : 'text-[#777]' }}">({{ $r['requirement'] === 'required' ? 'mandatory' : 'optional' }})</span></p>
            @if ($r['note'])<p class="mt-1 text-[13px] text-[#666]">{{ $r['note'] }}</p>@elseif ($r['kind']->description)<p class="mt-1 text-[13px] text-[#666]">{{ $r['kind']->description }}</p>@endif
            @if ($r['kind']->template_path)<a href="{{ $ctx->route('documents.template', $r['kind']) }}" class="mt-2 inline-flex items-center gap-1.5 border border-[#428bb6] bg-white px-3 py-1 text-[12px] font-semibold text-[#428bb6] hover:bg-[#eef6fb]"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>Download blank form<span class="sr-only"> for {{ $r['kind']->name }}</span></a>@endif
            <p class="mt-2 text-[13px]"><span class="font-semibold {{ $r['state'] === 'ready' ? 'text-[#2f8f4e]' : 'text-[#777]' }}">{{ match (true) { $r['state'] === 'ready' => '✓ Uploaded', $r['state'] === 'scanning' => 'Being checked', (bool) $r['reason'] => 'Cannot provide now', default => 'Not uploaded yet' } }}</span></p>
        </div>

        <div class="min-w-0">
            @if ($r['documents']->isNotEmpty())
                <ul class="flex flex-wrap justify-end gap-3">
                    @foreach ($r['documents'] as $d)
                        @php($file = $d->currentVersion?->original_name ?? $d->title)
                        <li class="w-[88px] text-center text-[11px] leading-tight text-[#555]">
                            @include('student.wizard._file-icon', ['name' => $file])
                            <span class="mx-auto block break-all" title="{{ $file }}">{{ \Illuminate\Support\Str::limit($file, 22) }}</span>
                            @if ($d->currentVersion?->scan_status === \App\Enums\ScanStatus::Rejected)<span class="block text-[#d9534f]">Rejected by the virus check</span>@elseif ($d->currentVersion?->scan_status === \App\Enums\ScanStatus::Pending)<span class="block text-[#d97706]">Being checked</span>@endif
                            <span class="mt-0.5 flex justify-center gap-2">
                                @if ($d->currentVersion?->isDownloadable())<a class="text-[#428bb6] hover:underline" href="{{ $ctx->route('documents.download', $d) }}">Download</a>@endif
                                @unless ($locked)<form method="POST" action="{{ $ctx->route('documents.destroy', $d) }}" onsubmit="return confirm('Remove this file?')">@csrf @method('DELETE')<input type="hidden" name="back" value="1"><input type="hidden" name="confirm" value="1"><button class="text-[#d9534f] hover:underline" aria-label="Remove {{ $file }}">Remove</button></form>@endunless
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @unless ($locked)
        <form method="POST" action="{{ $ctx->route('documents.store') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-2">
            @csrf
            <input type="hidden" name="type" value="{{ $r['kind']->key }}"><input type="hidden" name="acknowledge" value="1">
            <label for="up-{{ $r['kind']->key }}" class="sr-only">Upload files for {{ $r['kind']->name }}</label>
            <input id="up-{{ $r['kind']->key }}" type="file" name="files[]" multiple required accept=".pdf,.docx,.jpg,.jpeg,.png" class="max-w-full border border-[#ccc] bg-white px-2 py-1 text-[12px]">
            <button class="bg-[#0b4a8f] px-4 py-1.5 text-[13px] font-semibold text-white hover:bg-[#093d75]">Upload</button>
        </form>

        @if ($r['requirement'] === 'required')
            <form method="POST" action="{{ $ctx->route('applications.reasons', $plan) }}" class="mt-3 border-t border-[#ddd] pt-3" x-data="{ cannot: {{ $r['reason'] || old('cannot.'.$r['kind']->id) ? 'true' : 'false' }} }">
                @csrf @method('PUT')
                <label class="flex items-start gap-2 text-[13px] text-[#555]"><input type="checkbox" name="cannot[{{ $r['kind']->id }}]" value="1" x-model="cannot" class="mt-0.5"> <span>Currently cannot provide the document, enter the reason:</span></label>
                <div x-show="cannot" x-cloak class="mt-2">
                    <label for="why-{{ $r['kind']->id }}" class="sr-only">Reason you cannot provide {{ $r['kind']->name }}</label>
                    <textarea id="why-{{ $r['kind']->id }}" name="reason[{{ $r['kind']->id }}]" rows="2" maxlength="500" class="w-full border border-[#ccc] bg-white px-2 py-1.5 text-[13px] @error('reason.'.$r['kind']->id) border-[#d9534f] @enderror">{{ old('reason.'.$r['kind']->id, $r['reason']) }}</textarea>
                    @error('reason.'.$r['kind']->id)<p class="mt-1 text-[13px] text-[#d9534f]" role="alert">{{ $message }}</p>@enderror
                    <button class="mt-2 border border-[#bbb] bg-white px-4 py-1.5 text-[13px] hover:bg-[#f5f5f5]">Save reason</button>
                </div>
                <noscript><button class="mt-2 border border-[#bbb] bg-white px-4 py-1.5 text-[13px]">Save</button></noscript>
            </form>
        @endif
    @else
        @if ($r['reason'])<p class="mt-2 text-[13px] text-[#666]">Cannot provide now: {{ $r['reason'] }}</p>@endif
    @endunless
</li>
