<x-layouts.student title="Dashboard">
    <h1 class="text-2xl font-extrabold">Welcome, {{ \Illuminate\Support\Str::before($user->name, ' ') }}</h1>
    <p class="mt-1 text-sm text-muted">Your private workspace: plan each application, keep your documents and track what is done.</p>

    @php($started = [
        [1, 'Fill in your application form', $formPercent >= 100, $formPercent.'% complete. You do this once for every scholarship.', route('student.form.show'), $formPercent >= 100 ? 'Review' : 'Fill in'],
        [2, 'Choose the scholarships you want', $plans->isNotEmpty(), $plans->count().' chosen', route('student.choose.index'), 'Choose'],
        [3, 'Upload the documents each one needs', $plans->isNotEmpty() && $docsMissing === 0, $plans->isEmpty() ? 'Choose a scholarship first' : ($docsMissing === 0 ? 'All mandatory documents provided' : $docsMissing.' mandatory documents still missing'), route('student.applications.index'), 'Open'],
    ])
    @if (collect($started)->contains(fn ($s) => ! $s[2]))
        <section class="card mt-5 p-5" aria-labelledby="gs"><h2 id="gs" class="text-lg font-bold">Getting started</h2>
            <ol class="mt-3 space-y-3">
                @foreach ($started as [$n, $label, $done, $detail, $url, $cta])
                    <li class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-start gap-3"><span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $done ? 'bg-navy-700 text-white' : 'border border-slate-400' }}" aria-hidden="true">{{ $done ? "✓" : $n }}</span>
                            <div><p class="font-medium">{{ $label }}@if ($done) <span class="sr-only">(done)</span>@endif</p><p class="text-xs text-muted">{{ $detail }}</p></div></div>
                        <a class="{{ $done ? 'btn-outline' : 'btn-primary' }} !py-1.5" href="{{ $url }}">{{ $cta }}</a>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('student.saved.index') }}" class="card p-4 hover:border-navy-500"><p class="text-3xl font-extrabold">{{ $savedCount }}</p><p class="text-sm text-muted">Saved scholarships</p></a>
        <a href="{{ route('student.applications.index') }}" class="card p-4 hover:border-navy-500"><p class="text-3xl font-extrabold">{{ $plans->count() }}</p><p class="text-sm text-muted">Application plans</p></a>
        <a href="{{ route('student.documents.index') }}" class="card p-4 hover:border-navy-500"><p class="text-3xl font-extrabold">{{ $pendingScans }}</p><p class="text-sm text-muted">Documents pending scan</p></a>
        <a href="{{ route('student.notifications.index') }}" class="card p-4 hover:border-navy-500"><p class="text-3xl font-extrabold">{{ $unread }}</p><p class="text-sm text-muted">Unread notifications</p></a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card p-5 lg:col-span-2" aria-labelledby="cs"><h2 id="cs" class="text-lg font-bold">Cases and fees</h2>
            @php($owing = $cases->flatMap->invoices->where('status', 'issued'))
            @forelse ($cases as $c)
                <a class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3 first:border-0 first:pt-0 hover:underline" href="{{ route('student.cases.show', $c) }}">
                    <span>{{ $c->title }}@if ($c->awaiting_reply) <span class="badge-neutral">Waiting for your counsellor's reply</span>@endif</span>
                    <span class="badge-neutral">{{ $c->statusLabel() }}</span></a>
            @empty
                <p class="mt-2 text-sm text-muted">SAS has not opened a case for you yet. When it does, its status and fees appear here.</p>
            @endforelse
            @if ($owing->isNotEmpty())
                <p class="mt-3 text-sm"><strong>{{ $owing->count() }}</strong> {{ \Illuminate\Support\Str::plural('fee', $owing->count()) }} awaiting payment, <strong>US${{ number_format($owing->sum('amount_cents') / 100, 2) }}</strong> in total. <a class="text-navy-700 underline" href="{{ route('student.cases.index') }}">See how to pay</a></p>
            @endif
        </section>

        <section class="card p-5" aria-labelledby="up"><h2 id="up" class="text-lg font-bold">Upcoming confirmed deadlines</h2>
            @forelse ($upcoming as $c)
                <div class="mt-3 border-t border-line pt-3 first:border-0 first:pt-0"><a class="font-semibold hover:underline" href="{{ route('scholarships.show', [$c->programme->slug, $c->slug]) }}">{{ $c->label() }}</a><x-deadline :cycle="$c" class="mt-1" /></div>
            @empty
                <p class="mt-2 text-sm text-muted">No confirmed upcoming deadlines among your saved or planned scholarships.</p>
            @endforelse
            @if ($unconfirmed->isNotEmpty())
                <h3 class="mt-4 text-sm font-semibold">Deadline not confirmed</h3>
                <ul class="mt-1 list-disc pl-5 text-sm text-muted">@foreach ($unconfirmed as $c)<li><a class="underline" href="{{ route('scholarships.show', [$c->programme->slug, $c->slug]) }}">{{ $c->label() }}</a>: the date is not confirmed, so no reminders are scheduled</li>@endforeach</ul>
            @endif
        </section>

        <section class="card p-5" aria-labelledby="prof"><h2 id="prof" class="text-lg font-bold">Your profile</h2>
            @if (count($missing))
                <p class="mt-2 text-sm">Add these to see how scholarships compare with your details (all optional):</p>
                <ul class="mt-2 list-disc pl-5 text-sm text-muted">@foreach ($missing as $label)<li>{{ $label }}</li>@endforeach</ul>
            @else
                <p class="mt-2 text-sm">Your profile is complete.</p>
            @endif
            <a href="{{ route('student.profile.edit') }}" class="btn-outline mt-3">Edit profile</a>
        </section>

        <section class="card p-5" aria-labelledby="pl"><h2 id="pl" class="text-lg font-bold">Application plans</h2>
            @forelse ($plans->take(5) as $p)
                <a class="mt-3 flex items-center justify-between gap-2 border-t border-line pt-3 first:border-0 first:pt-0 hover:underline" href="{{ route('student.applications.show', $p) }}"><span>{{ $p->cycle->label() }}@if ($docs[$p->id]['required']) <span class="block text-xs text-muted">{{ $docs[$p->id]['ready'] }} of {{ $docs[$p->id]['required'] }} mandatory documents</span>@endif</span><span class="badge-neutral">{{ $p->status->label() }}</span></a>
            @empty
                <p class="mt-2 text-sm text-muted">No plans yet. Open a scholarship and choose “Track this application”.</p>
            @endforelse
        </section>

        <section class="card p-5" aria-labelledby="en"><h2 id="en" class="text-lg font-bold">Recent enquiries</h2>
            @forelse ($enquiries as $e)
                <a class="mt-3 flex items-center justify-between gap-2 border-t border-line pt-3 first:border-0 first:pt-0 hover:underline" href="{{ route('enquiries.show', $e) }}"><span>{{ $e->reference }}</span><span class="badge-neutral">{{ $e->status->label() }}</span></a>
            @empty
                <p class="mt-2 text-sm text-muted">You have not sent any enquiries.</p>
            @endforelse
            <a href="{{ route('contact') }}" class="btn-outline mt-3">New enquiry</a>
        </section>
    </div>
</x-layouts.student>
