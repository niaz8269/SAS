<x-dynamic-component :component="$ctx->layout()" :title="$cycle->displayName()">
    @include('student.wizard._header')

    @php
        // What the scholarship itself asks for comes first; documents it does not mention are tucked away.
        $main = collect($rows)->filter(fn ($r) => $r['requirement'] === 'required' || $r['ruled'] || $r['kind']->template_path)->sortBy(fn ($r) => $r['requirement'] === 'required' ? 0 : 1)->values();
        $other = collect($rows)->reject(fn ($r) => $r['requirement'] === 'required' || $r['ruled'] || $r['kind']->template_path)->values();
    @endphp

    @foreach (app(\App\Domain\Documents\DocumentHealth::class)->warnings($student) as $w)
        <div class="mt-5 rounded-xl border p-4 text-base {{ $w['level'] === 'urgent' ? 'border-red-300 bg-red-50 text-red-900' : 'border-gold bg-gold/10 text-ink' }}" role="alert">{{ $w['text'] }}</div>
    @endforeach

    @if ($multipleLevels && ! $levelId)
        <div class="mt-5 rounded-xl border border-gold bg-gold/10 p-4 text-base text-ink">The list depends on the degree. <a class="underline" href="{{ $ctx->route('applications.show', $plan) }}#level">Choose the degree and language</a> first.</div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[16rem_1fr]">
        <aside class="lg:sticky lg:top-24 lg:self-start">
            <div class="card p-4 text-sm">
                <h2 class="text-base font-bold text-ink">Before you upload</h2>
                <ol class="mt-3 list-decimal space-y-3 pl-5 text-muted">
                    <li>Upload each document as described next to it, to avoid delays.</li>
                    <li>Make sure scans and photos are clearly readable and can be printed.</li>
                    <li>Accepted formats: PDF, DOCX, JPG and PNG.</li>
                    <li>Each file can be up to {{ (int) (config('sas.uploads.max_kb') / 1024) }} MB. A slot takes several files.</li>
                    <li>A document you upload once is used for every scholarship that asks for it.</li>
                </ol>
            </div>
            <div class="card mt-3 p-4 text-sm">
                @if ($docProgress['required'] === 0) <strong>No document is mandatory.</strong>
                @else <strong>{{ $docProgress['ready'] }} of {{ $docProgress['required'] }}</strong> mandatory documents done @if ($docProgress['excused']) ({{ $docProgress['excused'] }} with a reason)@endif.
                    @if ($docProgress['missing'])<p class="mt-1 text-muted">Still needed: {{ implode(', ', $docProgress['missing']) }}.</p>@endif @endif
            </div>
        </aside>

        <div>
            <h2 class="border-b border-line pb-2 text-xl font-bold">Documents for this scholarship</h2>
            @if ($errors->has('file') || $errors->has('files') || $errors->has('type'))<p class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-base text-red-900" role="alert">{{ $errors->first('files') ?: ($errors->first('file') ?: $errors->first('type')) }}</p>@endif
            <ul class="mt-4 space-y-3">
                @foreach ($main as $i => $r)@include('student.wizard._material-slot', ['r' => $r, 'n' => $i + 1])@endforeach
            </ul>
            @if ($other->isNotEmpty())
                <details class="mt-5">
                    <summary class="cursor-pointer text-base text-navy-700 underline">Other documents you can add (this scholarship does not ask for them) &middot; {{ $other->count() }}</summary>
                    <ul class="mt-3 space-y-3">
                        @foreach ($other as $i => $r)@include('student.wizard._material-slot', ['r' => $r, 'n' => $main->count() + $i + 1])@endforeach
                    </ul>
                </details>
            @endif

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <a class="btn-outline" href="{{ $ctx->route('applications.form', $plan) }}">Previous</a>
                <a class="btn-primary" href="{{ $ctx->route('applications.submit', $plan) }}">Next: Send to SAS</a>
            </div>
        </div>
    </div>
</x-dynamic-component>
