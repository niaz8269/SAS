{{-- Choose a file, then press Upload. The type is fixed by the document row. --}}
<form method="POST" action="{{ route('student.documents.store') }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-end gap-2">
    @csrf
    <input type="hidden" name="type" value="{{ $r['kind']->key }}"><input type="hidden" name="acknowledge" value="1">
    <div><label for="up-{{ $r['kind']->key }}" class="text-xs font-medium">Choose one or more files (PDF, photo or Word)</label><input id="up-{{ $r['kind']->key }}" type="file" name="files[]" multiple required accept=".pdf,.docx,.jpg,.jpeg,.png" class="input mt-1 !py-1.5 text-xs"></div>
    <button class="btn-primary !py-1.5">Upload</button>
</form>
