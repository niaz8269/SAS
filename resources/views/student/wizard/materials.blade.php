<x-dynamic-component :component="$ctx->layout()" :title="$cycle->displayName()">
    @include('student.wizard._header')

    @php
        // What the scholarship itself asks for comes first; documents it does not mention are tucked away.
        $main = collect($rows)->filter(fn ($r) => $r['requirement'] === 'required' || $r['ruled'] || $r['kind']->template_path)->sortBy(fn ($r) => $r['requirement'] === 'required' ? 0 : 1)->values();
        $other = collect($rows)->reject(fn ($r) => $r['requirement'] === 'required' || $r['ruled'] || $r['kind']->template_path)->values();
    @endphp

    @foreach (app(\App\Domain\Documents\DocumentHealth::class)->warnings($student) as $w)
        <div class="mx-auto mt-5 max-w-4xl border p-4 text-[0.875rem] {{ $w['level'] === 'urgent' ? 'border-[#d9534f] bg-[#fdecea] text-[#a94442]' : 'border-[#f0b46a] bg-[#fdebd0] text-[#b4530b]' }}" role="alert">{{ $w['text'] }}</div>
    @endforeach

    @if ($multipleLevels && ! $levelId)
        <div class="mx-auto mt-5 max-w-4xl border border-[#f0b46a] bg-[#fdebd0] p-4 text-[0.875rem] text-[#b4530b]">The list depends on the degree. <a class="underline" href="{{ $ctx->route('applications.show', $plan) }}#level">Choose the degree in Apply Now</a> first.</div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[15.625rem_1fr]">
        <aside class="lg:sticky lg:top-4 lg:self-start">
            <div class="border border-[#ddd] bg-[#f7f7f7] p-4 text-[0.8125rem]">
                <h2 class="text-[0.9375rem] font-bold text-[#222]">Notice</h2>
                <ol class="mt-3 list-decimal space-y-3 pl-4 text-[#555]">
                    <li>To avoid delays in admission, please upload each document according to the description in the column on the right.</li>
                    <li>Please make sure the scanned or photographed documents are clearly readable and printable.</li>
                    <li>The system accepts these formats: PDF, DOCX, JPG and PNG.</li>
                    <li>Each uploaded file cannot exceed {{ (int) (config('sas.uploads.max_kb') / 1024) }}M. A slot takes several files.</li>
                    <li>A document you upload once is used for every scholarship that asks for it.</li>
                </ol>
            </div>
            <div class="mt-3 border border-[#ddd] bg-white p-4 text-[0.8125rem]">
                @if ($docProgress['required'] === 0) <strong>No document is mandatory.</strong>
                @else <strong>{{ $docProgress['ready'] }} of {{ $docProgress['required'] }}</strong> mandatory documents done @if ($docProgress['excused']) ({{ $docProgress['excused'] }} with a reason)@endif.
                    @if ($docProgress['missing'])<p class="mt-1 text-[0.75rem] text-[#777]">Still needed: {{ implode(', ', $docProgress['missing']) }}.</p>@endif @endif
            </div>
        </aside>

        <div>
            <h2 class="border-b border-[#cfd8e3] pb-2 text-[1.125rem] text-[#0b6ec7]">Upload Materials</h2>
            @if ($errors->has('file') || $errors->has('files') || $errors->has('type'))<p class="mt-3 border border-[#d9534f] bg-[#fdecea] p-3 text-[0.8125rem] text-[#a94442]" role="alert">{{ $errors->first('files') ?: ($errors->first('file') ?: $errors->first('type')) }}</p>@endif
            <ul class="mt-4 space-y-3">
                @foreach ($main as $i => $r)@include('student.wizard._material-slot', ['r' => $r, 'n' => $i + 1])@endforeach
            </ul>
            @if ($other->isNotEmpty())
                <details class="mt-5">
                    <summary class="cursor-pointer text-[0.875rem] text-[#0b6ec7]">Other documents you can add (this scholarship does not ask for them) &middot; {{ $other->count() }}</summary>
                    <ul class="mt-3 space-y-3">
                        @foreach ($other as $i => $r)@include('student.wizard._material-slot', ['r' => $r, 'n' => $main->count() + $i + 1])@endforeach
                    </ul>
                </details>
            @endif

            <div class="mt-6 flex flex-wrap items-center justify-center gap-3 text-[0.9375rem]">
                <a class="border border-[#bbb] bg-white px-8 py-2.5 hover:bg-[#f5f5f5]" href="{{ $ctx->route('applications.form', $plan) }}">Previous</a>
                <a class="bg-[#0b4a8f] px-10 py-2.5 font-semibold text-white hover:bg-[#093d75]" href="{{ $ctx->route('applications.submit', $plan) }}">Next: Apply Submit</a>
            </div>
        </div>
    </div>
</x-dynamic-component>
