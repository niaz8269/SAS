<x-layouts.student title="Documents">
    <h1 class="text-2xl font-extrabold">Document vault</h1>
    <p class="mt-1 text-sm text-muted">Private to you. Every upload is held in quarantine and scanned for malware before it becomes available. Only you can download your files.</p>

    <form method="POST" action="{{ route('student.documents.store') }}" enctype="multipart/form-data" class="card mt-5 space-y-3 p-5" novalidate>
        @csrf
        <h2 class="text-lg font-bold">Upload a document</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label for="file" class="label">Files (PDF, DOCX, JPEG or PNG, you can choose several, up to {{ config('sas.uploads.max_kb') / 1024 }} MB)</label><input id="file" type="file" name="files[]" multiple required accept=".pdf,.docx,.jpg,.jpeg,.png" class="input @error('file') input-error @enderror @error('files') input-error @enderror">@error('file')<p class="field-error" role="alert">{{ $message }}</p>@enderror @error('files')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            <div><label for="type" class="label">Type</label><select id="type" name="type" class="input">@foreach (\App\Models\DocumentKind::options() as $k => $l)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $l }}</option>@endforeach</select></div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="combine" value="1" class="rounded border-slate-300"> Combine several photos of one document into a single PDF</label>
        <div><label for="title" class="label">Title (optional)</label><input id="title" name="title" value="{{ old('title') }}" class="input"></div>
        <div><label class="flex items-start gap-2 text-sm"><input type="checkbox" name="acknowledge" value="1" class="mt-1 rounded border-slate-300 text-navy-700"><span>I understand this document is stored privately, scanned for malware, and not shared unless I link it to a case or plan.</span></label>@error('acknowledge')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
        <button class="btn-primary">Upload</button>
        <p class="text-xs text-muted">Storage used: {{ number_format($used / 1048576, 1) }} MB of {{ number_format($quota / 1048576) }} MB. Please do not upload anything you are not comfortable storing here.</p>
    </form>

    <section class="mt-6" aria-labelledby="mine"><h2 id="mine" class="text-lg font-bold">Your documents</h2>
        @forelse ($documents as $d)
            @php($v = $d->currentVersion)
            <div class="card mt-3 p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div><p class="font-semibold">{{ $d->title }}</p><p class="text-xs text-muted">{{ \App\Models\DocumentKind::label($d->type) }} · version {{ $v?->version }} · {{ $v ? number_format($v->size / 1024) : 0 }} KB · {{ $v?->original_name }}</p></div>
                    <div class="flex items-center gap-2">
                        @if ($v)<span class="{{ match($v->scan_status->value) { 'available' => 'badge-green', 'rejected' => 'badge-alert', default => 'badge-warn' } }}">{{ $v->scan_status->label() }}</span>@endif
                        @if ($v?->isDownloadable())<a class="btn-outline !py-1.5" href="{{ route('student.documents.download', $d) }}">Download</a>@endif
                    </div>
                </div>
                @foreach ($d->comments->sortByDesc('id')->take(2) as $note)<p class="mt-2 rounded-md border border-warn/40 bg-warn-soft px-3 py-2 text-sm">Comment from {{ $note->author?->name ?: 'SAS' }} ({{ $note->created_at->format('j M') }}): {{ $note->body }}</p>@endforeach
                @if ($v?->scan_status === \App\Enums\ScanStatus::Rejected)<p class="mt-2 text-sm text-alert">This file was rejected by the security scan and has been removed. Please upload a clean copy.</p>@endif
                <div class="mt-3 flex flex-wrap items-end gap-3 border-t border-line pt-3">
                    <form method="POST" action="{{ route('student.documents.versions.store', $d) }}" enctype="multipart/form-data" class="flex items-end gap-2">@csrf
                        <div><label for="ver-{{ $d->id }}" class="text-xs font-medium">Upload new version</label><input id="ver-{{ $d->id }}" type="file" name="file" required accept=".pdf,.docx,.jpg,.jpeg,.png" class="input !py-1.5 text-xs"></div><button class="btn-outline !py-1.5">Add</button></form>
                    <form method="POST" action="{{ route('student.documents.destroy', $d) }}" onsubmit="return confirm('Delete this document and all its versions? This cannot be undone.')" class="flex items-center gap-2">@csrf @method('DELETE')
                        @if ($d->associations->isNotEmpty())<label class="flex items-center gap-1 text-xs"><input type="checkbox" name="confirm" value="1"> Also remove {{ $d->associations->count() }} plan link(s)</label>@endif
                        <button class="btn-danger !py-1.5">Delete</button></form>
                </div>
            </div>
        @empty
            <p class="card mt-3 p-6 text-sm text-muted">No documents yet.</p>
        @endforelse
    </section>
</x-layouts.student>
