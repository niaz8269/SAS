{{-- Documents a scholarship needs, from the admin's rules. Expects $rows, $docProgress, $levels, $levelId, $levelAction. A document uploaded once counts for every scholarship that asks for it. --}}
@php
    // What the scholarship itself asks for comes first; documents it does not mention are tucked away.
    $main = collect($rows)->filter(fn ($r) => $r['requirement'] === 'required' || $r['ruled'])->sortBy(fn ($r) => $r['requirement'] === 'required' ? 0 : 1)->values();
    $other = collect($rows)->reject(fn ($r) => $r['requirement'] === 'required' || $r['ruled'])->values();
@endphp
<section id="checklist" class="card mt-5 p-5" aria-labelledby="check-h">
    <h2 id="check-h" class="font-bold">Documents needed for this scholarship</h2>
    <form method="POST" action="{{ $levelAction }}" class="mt-2 flex flex-wrap items-end gap-2">
        @csrf @method('PUT')
        <div><label for="level" class="label">Degree level you are applying for</label>
            <select id="level" name="degree_level_id" class="input"><option value="">Not chosen yet</option>@foreach ($levels as $l)<option value="{{ $l->id }}" @selected($levelId === $l->id)>{{ $l->name }}</option>@endforeach</select></div>
        <button class="btn-outline">Save level</button>
    </form>
    <p class="mt-3 text-sm {{ $docProgress['missing'] ? '' : 'text-muted' }}">
        @if ($docProgress['required'] === 0) No document is mandatory for this application.
        @elseif ($docProgress['missing']) <strong>{{ $docProgress['ready'] }} of {{ $docProgress['required'] }}</strong> mandatory documents provided. Missing: {{ implode(', ', $docProgress['missing']) }}.
        @else All {{ $docProgress['required'] }} mandatory documents are provided. @endif
    </p>
    <p class="mt-1 text-xs text-muted">The list depends on the scholarship and degree level. A document you upload once counts for every scholarship that asks for it. Files are stored privately and scanned for malware first. Use clear scans of the originals.</p>
    @if ($errors->has('file') || $errors->has('type'))<p class="mt-3 text-sm text-alert" role="alert">{{ $errors->first('file') ?: $errors->first('type') }}</p>@endif
    <ul class="mt-3 divide-y divide-line">
        @foreach ($main as $r)@include('student._document-row', ['r' => $r])@endforeach
    </ul>
    @if ($other->isNotEmpty())
        <details class="mt-4">
            <summary class="cursor-pointer text-sm font-medium text-navy-700">Other documents you can add (this scholarship does not ask for them) &middot; {{ $other->count() }}</summary>
            <ul class="mt-2 divide-y divide-line">
                @foreach ($other as $r)@include('student._document-row', ['r' => $r])@endforeach
            </ul>
        </details>
    @endif
</section>
