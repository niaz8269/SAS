{{-- Documents a scholarship needs, from the admin's rules. Expects $rows, $docProgress, $levels, $levelId, $levelAction. A document uploaded once counts for every scholarship that asks for it. --}}
@php
    // What the scholarship itself asks for comes first; documents it does not mention are tucked away.
    $main = collect($rows)->filter(fn ($r) => $r['requirement'] === 'required' || $r['ruled'])->sortBy(fn ($r) => $r['requirement'] === 'required' ? 0 : 1)->values();
    $other = collect($rows)->reject(fn ($r) => $r['requirement'] === 'required' || $r['ruled'])->values();
    $askLevel = count($levels) > 1; // a scholarship with a single degree needs no question
@endphp
<section id="checklist" class="card mt-5 p-5" aria-labelledby="check-h">
    <h2 id="check-h" class="text-lg font-bold">Documents for this scholarship</h2>

    @if ($askLevel)
        <form method="POST" action="{{ $levelAction }}" class="mt-3 flex flex-wrap items-end gap-2 rounded-lg {{ $levelId ? '' : 'border border-gold/60 bg-gold-soft p-3' }}">
            @csrf @method('PUT')
            <div><label for="level" class="label">{{ $levelId ? 'Degree you are applying for' : 'Which degree are you applying for? The list below depends on it.' }}</label>
                <select id="level" name="degree_level_id" class="input"><option value="">Choose a degree</option>@foreach ($levels as $l)<option value="{{ $l->id }}" @selected($levelId === $l->id)>{{ $l->name }}</option>@endforeach</select></div>
            <button class="btn-outline">Save</button>
        </form>
    @endif

    <div class="mt-4 rounded-lg bg-ivory-deep p-3 text-sm">
        @if ($docProgress['required'] === 0) <strong>No document is mandatory for this scholarship.</strong>
        @elseif ($docProgress['missing']) <strong>{{ $docProgress['ready'] }} of {{ $docProgress['required'] }} mandatory documents provided.</strong> Still needed: {{ implode(', ', $docProgress['missing']) }}.
        @else <strong>✓ All {{ $docProgress['required'] }} mandatory documents are provided.</strong> @endif
        @if ($docProgress['required'] > 0)
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $docProgress['required'] }}" aria-valuenow="{{ $docProgress['ready'] }}" aria-label="Mandatory documents provided"><div class="h-full bg-navy-600" style="width: {{ round($docProgress['ready'] / $docProgress['required'] * 100) }}%"></div></div>
        @endif
    </div>
    <p class="mt-2 text-xs text-muted">For each document, choose a file and press Upload. A document you upload once is used for every scholarship that asks for it. Files are private and checked for viruses first. Use clear scans of the originals.</p>
    @if ($errors->has('file') || $errors->has('type'))<p class="mt-3 text-sm text-alert" role="alert">{{ $errors->first('file') ?: $errors->first('type') }}</p>@endif
    <ul class="mt-2 divide-y divide-line">
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
