<x-dynamic-component :component="$ctx->layout()" :title="$cycle->displayName()">
    @include('student.wizard._header')
    <h2 class="mt-5 text-lg font-bold">Step 3: Upload materials</h2>

    @php
        // What the scholarship itself asks for comes first; documents it does not mention are tucked away.
        $main = collect($rows)->filter(fn ($r) => $r['requirement'] === 'required' || $r['ruled'])->sortBy(fn ($r) => $r['requirement'] === 'required' ? 0 : 1)->values();
        $other = collect($rows)->reject(fn ($r) => $r['requirement'] === 'required' || $r['ruled'])->values();
    @endphp

    @if ($multipleLevels && ! $levelId)
        <div class="mt-4 rounded-lg border border-gold/60 bg-gold-soft p-4 text-sm">The list depends on the degree. <a class="text-navy-700 underline" href="{{ $ctx->route('applications.show', $plan) }}#level">Choose the degree in step 1</a> first.</div>
    @endif

    <div class="mt-4 grid gap-6 lg:grid-cols-[230px_1fr]">
        <aside class="lg:sticky lg:top-6 lg:self-start">
            <div class="card p-4 text-sm">
                <h3 class="font-bold">Notice</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-4 text-muted">
                    <li>To avoid delays, upload each document in the slot it belongs to.</li>
                    <li>Scanned or photographed documents must be clear, readable and printable.</li>
                    <li>Accepted formats: PDF, DOCX, JPG and PNG.</li>
                    <li>Each file can be up to {{ (int) (config('sas.uploads.max_kb') / 1024) }} MB. A slot takes several files.</li>
                    <li>A document you upload once is used for every scholarship that asks for it.</li>
                </ol>
            </div>
            <div class="card mt-3 p-4 text-sm">
                @if ($docProgress['required'] === 0) <strong>No document is mandatory.</strong>
                @else <strong>{{ $docProgress['ready'] }} of {{ $docProgress['required'] }}</strong> mandatory documents done @if ($docProgress['excused']) ({{ $docProgress['excused'] }} with a reason)@endif.
                    @if ($docProgress['missing'])<p class="mt-1 text-xs text-muted">Still needed: {{ implode(', ', $docProgress['missing']) }}.</p>@endif @endif
            </div>
        </aside>

        <div>
            @if ($errors->has('file') || $errors->has('files') || $errors->has('type'))<p class="mb-3 rounded-md border border-alert bg-alert-soft p-3 text-sm" role="alert">{{ $errors->first('files') ?: ($errors->first('file') ?: $errors->first('type')) }}</p>@endif
            <ul class="space-y-3">
                @foreach ($main as $i => $r)@include('student.wizard._material-slot', ['r' => $r, 'n' => $i + 1])@endforeach
            </ul>
            @if ($other->isNotEmpty())
                <details class="mt-5">
                    <summary class="cursor-pointer text-sm font-medium text-navy-700">Other documents you can add (this scholarship does not ask for them) &middot; {{ $other->count() }}</summary>
                    <ul class="mt-3 space-y-3">
                        @foreach ($other as $i => $r)@include('student.wizard._material-slot', ['r' => $r, 'n' => $main->count() + $i + 1])@endforeach
                    </ul>
                </details>
            @endif

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <a class="btn-outline" href="{{ $ctx->route('applications.form', $plan) }}">Previous</a>
                <a class="btn-primary" href="{{ $ctx->route('applications.submit', $plan) }}">Next: apply submit</a>
            </div>
        </div>
    </div>
</x-dynamic-component>
