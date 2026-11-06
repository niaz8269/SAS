<x-dynamic-component :component="$ctx->layout()" title="My applications">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ $ctx->isAgent() ? 'Applications of '.$student->name : 'My applications' }}</h1>
        <a class="btn-primary" href="{{ $ctx->route('choose.index') }}">+ Add application</a>
    </div>

    @foreach (app(\App\Domain\Documents\DocumentHealth::class)->warnings($student) as $w)
        <div class="mt-5 rounded-xl border p-4 text-base {{ $w['level'] === 'urgent' ? 'border-red-300 bg-red-50 text-red-900' : 'border-gold bg-gold/10 text-ink' }}" role="alert">{{ $w['text'] }}</div>
    @endforeach
    @php($journeyService = app(\App\Domain\Journey\Journey::class))
    @php($overdue = $journeyService->overdue($plans->pluck('id')))
    @if ($overdue->isNotEmpty())
        <div class="mt-5 rounded-xl border border-gold bg-gold/10 p-4 text-base text-ink" role="alert">
            <p class="font-semibold">{{ $overdue->count() }} {{ \Illuminate\Support\Str::plural('task', $overdue->count()) }} past the date:</p>
            <ul class="mt-1 list-disc pl-5">@foreach ($overdue->take(5) as $t)<li>{{ $t->title }} ({{ $t->plan->cycle->programme->title }}, was due {{ $t->due_on->format('j M') }}) <a class="underline" href="{{ $ctx->route('applications.journey', $t->plan) }}">Open</a></li>@endforeach</ul>
        </div>
    @endif

    <nav class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="Filter applications">
        @foreach (['all' => 'All', 'unfinished' => 'Not sent yet', 'finished' => 'Sent to SAS'] as $k => $l)
            <a href="{{ $ctx->route('applications.index', $k === 'all' ? [] : ['show' => $k]) }}" @if ($filter === $k) aria-current="page" @endif class="rounded-full border px-4 py-2 font-medium {{ $filter === $k ? 'border-navy-700 bg-navy-700 text-white' : 'border-line bg-white text-ink hover:bg-navy-50' }}">{{ $l }}</a>
        @endforeach
    </nav>

    <ul class="mt-4 space-y-3">
        @forelse ($plans as $p)
            @php($d = $docs[$p->id])
            @php($case = $cases[$p->cycle_id] ?? null)
            @php($formDone = $formPercent >= 100)
            {{-- One main button: the next thing to do for this application. --}}
            @php($next = ! $p->isEditable() ? ['View', 'applications.show'] : (! $formDone ? ['Continue: your details', 'applications.form'] : ($d['missing'] ? ['Continue: documents', 'applications.materials'] : ['Send to SAS', 'applications.submit'])))
            <li class="card p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a class="text-lg font-bold text-navy-800 hover:underline" href="{{ $ctx->route('applications.show', $p) }}">{{ $p->cycle->displayName() }}</a>
                        <p class="text-sm text-muted">{{ $p->degreeLevel?->name ?? ($p->cycle->degreeLevels->count() === 1 ? $p->cycle->degreeLevels->first()->name : 'Degree not chosen') }} &middot; Application number {{ $p->application_no }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-sm font-semibold {{ match ($p->stage) { 'passed' => 'bg-emerald-100 text-emerald-900', 'returned' => 'bg-red-100 text-red-900', 'submitted' => 'bg-navy-100 text-navy-900', default => 'bg-slate-100 text-ink' } }}">{{ $p->statusFor($case) }}</span>
                </div>
                @if ($p->review_remarks && in_array($p->stage, ['returned', 'passed'], true))
                    <p class="mt-3 whitespace-pre-line rounded-lg border {{ $p->stage === 'returned' ? 'border-red-300 bg-red-50 text-red-900' : 'border-line bg-slate-50 text-ink' }} p-3 text-base"><strong>Notes from SAS:</strong> {{ $p->review_remarks }}</p>
                @endif
                <p class="mt-3 text-base text-muted">Your details: {{ $formDone ? 'complete' : $formPercent.'% filled in' }} &middot; Documents: {{ $d['required'] === 0 ? 'none mandatory' : $d['ready'].' of '.$d['required'].' mandatory provided' }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <a class="{{ $p->stage === 'returned' ? 'btn-primary' : ($p->isEditable() ? 'btn-primary' : 'btn-outline') }}" href="{{ $ctx->route($next[1], $p) }}">{{ $p->stage === 'returned' ? 'Fix and send again' : $next[0] }}</a>
                    <details class="text-base">
                        <summary class="cursor-pointer text-navy-700 underline">More</summary>
                        <ul class="mt-2 space-y-1">
                            <li><a class="text-navy-700 underline" href="{{ $ctx->route('applications.preview', $p) }}" target="_blank" rel="noopener">Preview</a></li>
                            <li><a class="text-navy-700 underline" href="{{ $ctx->route('applications.download', $p) }}">Download Application Form</a></li>
                            <li><a class="text-navy-700 underline" href="{{ $ctx->route('applications.show', $p) }}">Degree and language</a></li>
                            <li>Journey: <a class="text-navy-700 underline" href="{{ $ctx->route('applications.journey', $p) }}">{{ $journeyService->label($journeyService->currentStage($p)) }}</a></li>
                        </ul>
                    </details>
                </div>
            </li>
        @empty
            <li class="card px-4 py-10 text-center text-base text-muted">No applications here yet. Press <strong>+ Add application</strong> to choose a scholarship.</li>
        @endforelse
    </ul>

    <section class="mt-5 rounded-xl border border-line bg-white p-5 text-base text-muted" aria-labelledby="legend-h">
        <h2 id="legend-h" class="font-semibold text-ink">What the statuses mean</h2>
        <ul class="mt-2 space-y-1">
            <li><strong class="text-ink">Not sent yet:</strong> you are still preparing it. Nothing has gone to SAS.</li>
            <li><strong class="text-ink">With SAS:</strong> SAS is checking your details and documents.</li>
            <li><strong class="text-ink">Needs changes:</strong> read the notes from SAS, fix what they ask, then send it again.</li>
            <li><strong class="text-ink">Passed:</strong> SAS found it complete and tells you the next step.</li>
        </ul>
    </section>
</x-dynamic-component>
