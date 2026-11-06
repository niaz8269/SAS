<x-layouts.student title="Home">
    <h1 class="text-2xl font-extrabold">Welcome, {{ \Illuminate\Support\Str::before($user->name, ' ') }}</h1>
    <p class="mt-1 text-base text-muted">Applying takes four steps. We show you what to do next.</p>

    <ol class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Your four steps">
        @foreach ($steps as $s)
            @php($isCurrent = $current && $current['n'] === $s['n'])
            <li>
                <a href="{{ $s['url'] }}" class="card flex h-full items-start gap-3 p-4 transition hover:border-navy-500 {{ $isCurrent ? 'border-navy-600 ring-2 ring-navy-600/20' : '' }}" @if ($isCurrent) aria-current="step" @endif>
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $s['done'] ? 'bg-navy-700 text-white' : ($isCurrent ? 'bg-gold text-navy-900' : 'border border-slate-400 text-muted') }}" aria-hidden="true">{{ $s['done'] ? '✓' : $s['n'] }}</span>
                    <span><span class="block font-semibold">Step {{ $s['n'] }}: {{ $s['title'] }}</span><span class="block text-sm text-muted">{{ $s['detail'] }}@if ($s['done']) <span class="sr-only">(done)</span>@endif</span></span>
                </a>
            </li>
        @endforeach
    </ol>

    @if ($returned->isNotEmpty())
        <section class="mt-5 rounded-xl border-2 border-gold bg-gold/10 p-5" aria-labelledby="fix-h">
            <h2 id="fix-h" class="text-lg font-bold">SAS sent {{ $returned->count() === 1 ? 'an application' : $returned->count().' applications' }} back to you</h2>
            <p class="mt-1 text-base text-muted">Read the notes, make the changes, then press Send to SAS again.</p>
            @foreach ($returned as $p)
                <div class="mt-3 rounded-lg border border-line bg-white p-4">
                    <p class="font-semibold">{{ $p->cycle->displayName() }}</p>
                    @if ($p->review_remarks)<p class="mt-1 whitespace-pre-line text-base">{{ $p->review_remarks }}</p>@endif
                    <a href="{{ route('student.applications.show', $p) }}" class="btn-primary mt-3">Fix it and send again</a>
                </div>
            @endforeach
        </section>
    @endif

    <section class="card mt-5 p-6" aria-labelledby="next-h">
        @if ($current)
            <p class="text-sm font-semibold uppercase tracking-wide text-navy-700">Your next step</p>
            <h2 id="next-h" class="mt-1 text-xl font-bold">
                @if ($current['n'] === 1) Tell us about yourself
                @elseif ($current['n'] === 2) Choose the scholarships you want to apply for
                @elseif ($current['n'] === 3) Upload the documents your scholarships need
                @else Send your application to SAS @endif
            </h2>
            <p class="mt-1 text-base text-muted">
                @if ($current['n'] === 1) Fill in your details once. Every scholarship you choose uses the same answers.
                @elseif ($current['n'] === 2) Press <strong>Apply</strong> next to each scholarship you want. You can choose more than one.
                @elseif ($current['n'] === 3) Each scholarship asks for different documents. Open one to see its list. A document you upload once is used for all of them.
                @else Press <strong>Send to SAS</strong>. SAS checks your form and documents, then passes the application or sends it back with notes. Nothing goes to the scholarship until SAS has passed it. @endif
            </p>
            <a href="{{ $current['url'] }}" class="btn-primary mt-4">
                @if ($current['n'] === 1) Fill in my details @elseif ($current['n'] === 2) Choose scholarships @elseif ($current['n'] === 3) Upload my documents @else Send to SAS @endif
            </a>
        @else
            <h2 id="next-h" class="text-xl font-bold">You are all set</h2>
            <p class="mt-1 text-base text-muted">Your details and documents are in, and every application is with SAS or passed. SAS tells you here what happens next. You can also choose another scholarship.</p>
            <div class="mt-4 flex flex-wrap gap-2"><a href="{{ route('student.applications.index') }}" class="btn-primary">My applications</a><a href="{{ route('student.choose.index') }}" class="btn-outline">Choose another scholarship</a></div>
        @endif
    </section>

    @if ($plans->isNotEmpty())
        <section class="mt-6" aria-labelledby="apps-h">
            <div class="flex items-center justify-between"><h2 id="apps-h" class="text-lg font-bold">My applications</h2><a class="text-sm text-navy-700 underline" href="{{ route('student.choose.index') }}">Choose another</a></div>
            @foreach ($plans as $p)
                <a href="{{ route('student.applications.show', $p) }}" class="card mt-3 flex flex-wrap items-center justify-between gap-2 p-4 hover:border-navy-500">
                    <span><span class="block font-semibold">{{ $p->cycle->displayName() }}</span>
                        <span class="block text-sm text-muted"><strong class="{{ $p->stage === 'returned' ? 'text-red-700' : 'text-ink' }}">{{ $p->applicantLabel() }}</strong> &middot; @if ($docs[$p->id]['required'] === 0) No mandatory documents @else {{ $docs[$p->id]['ready'] }} of {{ $docs[$p->id]['required'] }} mandatory documents provided @endif</span></span>
                    <span class="btn-outline !py-1.5">Open</span>
                </a>
            @endforeach
        </section>
    @endif

    @if ($upcoming->isNotEmpty())
        <section class="mt-6" aria-labelledby="dl-h"><h2 id="dl-h" class="text-lg font-bold">Coming up</h2>
            @foreach ($upcoming as $c)
                <div class="card mt-3 p-4"><a class="font-semibold hover:underline" href="{{ route('scholarships.show', [$c->programme->slug, $c->slug]) }}">{{ $c->displayName() }}</a><x-deadline :cycle="$c" class="mt-1" /></div>
            @endforeach
        </section>
    @endif

    @if ($cases->isNotEmpty())
        <section class="mt-6" aria-labelledby="sup-h"><h2 id="sup-h" class="text-lg font-bold">SAS is helping you with</h2>
            @foreach ($cases as $c)
                <a href="{{ route('student.cases.show', $c) }}" class="card mt-3 flex flex-wrap items-center justify-between gap-2 p-4 hover:border-navy-500">
                    <span><span class="block font-semibold">{{ $c->title }}</span><span class="block text-sm text-muted">{{ $c->statusLabel() }}@if ($c->awaiting_reply) &middot; waiting for your counsellor's reply @endif
                        @php($owed = $c->invoices->where('status', 'issued')->sum('amount_cents'))@if ($owed) &middot; <strong>{{ \App\Models\Invoice::money((int) $owed) }} to pay</strong>@endif</span></span>
                    <span class="btn-outline !py-1.5">Open</span>
                </a>
            @endforeach
        </section>
    @endif
</x-layouts.student>
