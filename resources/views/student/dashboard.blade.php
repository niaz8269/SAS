<x-layouts.student title="Home">
    <h1 class="text-2xl font-extrabold">Welcome, {{ \Illuminate\Support\Str::before($user->name, ' ') }}</h1>
    <p class="mt-1 text-sm text-muted">Applying takes three steps. We show you what to do next.</p>

    <ol class="mt-5 grid gap-3 sm:grid-cols-3" aria-label="Your three steps">
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

    <section class="card mt-5 p-6" aria-labelledby="next-h">
        @if ($current)
            <p class="text-sm font-semibold uppercase tracking-wide text-navy-700">Your next step</p>
            <h2 id="next-h" class="mt-1 text-xl font-bold">
                @if ($current['n'] === 1) Tell us about yourself
                @elseif ($current['n'] === 2) Choose the scholarships you want to apply for
                @else Upload the documents your scholarships need @endif
            </h2>
            <p class="mt-1 text-sm text-muted">
                @if ($current['n'] === 1) Fill in your details once. Every scholarship you choose uses the same answers.
                @elseif ($current['n'] === 2) Press <strong>Apply</strong> next to each scholarship you want. You can choose more than one.
                @else Each scholarship asks for different documents. Open one to see its list. A document you upload once is used for all of them. @endif
            </p>
            <a href="{{ $current['url'] }}" class="btn-primary mt-4">
                @if ($current['n'] === 1) Fill in my details @elseif ($current['n'] === 2) Choose scholarships @else Upload my documents @endif
            </a>
        @else
            <h2 id="next-h" class="text-xl font-bold">You are all set</h2>
            <p class="mt-1 text-sm text-muted">Your details and all mandatory documents are in. Open an application to ask SAS to review it, or choose another scholarship.</p>
            <div class="mt-4 flex flex-wrap gap-2"><a href="{{ route('student.applications.index') }}" class="btn-primary">My applications</a><a href="{{ route('student.choose.index') }}" class="btn-outline">Choose another scholarship</a></div>
        @endif
    </section>

    @if ($plans->isNotEmpty())
        <section class="mt-6" aria-labelledby="apps-h">
            <div class="flex items-center justify-between"><h2 id="apps-h" class="text-lg font-bold">My applications</h2><a class="text-sm text-navy-700 underline" href="{{ route('student.choose.index') }}">Choose another</a></div>
            @foreach ($plans as $p)
                <a href="{{ route('student.applications.show', $p) }}" class="card mt-3 flex flex-wrap items-center justify-between gap-2 p-4 hover:border-navy-500">
                    <span><span class="block font-semibold">{{ $p->cycle->displayName() }}</span>
                        <span class="block text-sm text-muted">@if ($docs[$p->id]['required'] === 0) No mandatory documents @else {{ $docs[$p->id]['ready'] }} of {{ $docs[$p->id]['required'] }} mandatory documents provided @endif</span></span>
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
