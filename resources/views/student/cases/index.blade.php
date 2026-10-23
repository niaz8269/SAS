<x-layouts.student title="Cases and fees">
    <h1 class="text-2xl font-extrabold">Cases and fees</h1>
    <p class="mt-1 text-sm text-muted">The applications SAS supports for you, where each one stands and what you owe. Your counsellor updates this page; you cannot change it yourself.</p>
    @forelse ($cases as $c)
        @php($due = $c->invoices->where('status', 'issued')->sum('amount_cents'))
        <a href="{{ route('student.cases.show', $c) }}" class="card mt-4 block p-5 transition hover:border-navy-500">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-bold">{{ $c->title }}</h2>
                <span class="badge-neutral">{{ $c->statusLabel() }}</span>
            </div>
            <p class="mt-2 text-sm text-muted">Counsellor: {{ $c->advisor?->name ?? 'Being assigned' }} &middot; opened {{ $c->opened_at?->format('j M Y') }}</p>
            @if ($due > 0)<p class="mt-2 text-sm font-semibold text-alert">Awaiting payment: {{ \App\Models\Invoice::money($due) }}</p>@endif
        </a>
    @empty
        <div class="card mt-4 p-8 text-center">
            <p class="font-semibold">No cases yet</p>
            <p class="mt-1 text-sm text-muted">When you ask SAS to support an application, your counsellor opens a case here and it appears with its fees.</p>
            <a href="{{ route('contact') }}" class="btn-primary mt-4">Send an enquiry</a>
        </div>
    @endforelse
</x-layouts.student>