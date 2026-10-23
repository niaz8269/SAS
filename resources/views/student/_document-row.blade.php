{{-- One document in a checklist: its rule, status, and an upload form. Expects $r from App\Domain\Documents\Requirements. --}}
<li class="py-3">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div><p class="font-medium">{{ $r['kind']->name }}
                <span class="{{ $r['requirement'] === 'required' ? 'badge-warn' : 'badge-neutral' }}">{{ $r['requirement'] === 'required' ? 'Mandatory' : 'Optional' }}</span></p>
            @if ($r['kind']->description)<p class="text-xs text-muted">{{ $r['kind']->description }}</p>@endif
            @if ($r['note'])<p class="text-xs text-muted">{{ $r['note'] }}@if ($r['source'] && str_starts_with($r['source'], 'https://')) <a class="text-navy-700 underline" rel="noopener" href="{{ $r['source'] }}">Official source</a>@endif</p>@endif</div>
        <span class="{{ match ($r['state']) { 'ready' => 'badge-green', 'scanning' => 'badge-warn', default => 'badge-neutral' } }}">{{ match ($r['state']) { 'ready' => 'Uploaded', 'scanning' => 'Being checked', default => 'Not uploaded' } }}</span>
    </div>
    @foreach ($r['documents'] as $d)<p class="mt-1 text-xs text-muted">{{ $d->title }}@if ($d->currentVersion) &middot; {{ $d->currentVersion->original_name }}@endif</p>@endforeach
    <form method="POST" action="{{ route('student.documents.store') }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-end gap-2">
        @csrf
        <input type="hidden" name="type" value="{{ $r['kind']->key }}"><input type="hidden" name="title" value="{{ $r['kind']->name }}"><input type="hidden" name="acknowledge" value="1">
        <div><label for="up-{{ $r['kind']->key }}" class="sr-only">Upload {{ $r['kind']->name }}</label><input id="up-{{ $r['kind']->key }}" type="file" name="file" required accept=".pdf,.docx,.jpg,.jpeg,.png" class="input !py-1.5 text-xs"></div>
        <button class="btn-outline !py-1.5">{{ $r['documents']->isEmpty() ? 'Upload' : 'Upload another' }}</button>
    </form>
</li>
