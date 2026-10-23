<x-layouts.student title="My enquiries">
    <div class="flex flex-wrap items-center justify-between gap-2"><h1 class="text-2xl font-extrabold">My enquiries</h1><a href="{{ route('contact') }}" class="btn-primary">New enquiry</a></div>
    <section class="mt-4" aria-labelledby="enq"><h2 id="enq" class="sr-only">Enquiries</h2>
        @forelse ($enquiries as $e)
            <a href="{{ route('enquiries.show', $e) }}" class="card mt-3 flex flex-wrap items-center justify-between gap-2 p-4 hover:border-navy-500"><span><strong>{{ $e->reference }}</strong> · {{ config('sas.enquiry_categories')[$e->category] ?? $e->category }}</span><span class="badge-neutral">{{ $e->status->label() }}</span></a>
        @empty
            <p class="card p-6 text-sm text-muted">No enquiries yet.</p>
        @endforelse
    </section>
    <section class="mt-8" aria-labelledby="cons"><h2 id="cons" class="text-lg font-bold">Consultation requests</h2>
        @forelse ($consultations as $c)
            <div class="card mt-3 p-4"><div class="flex flex-wrap items-center justify-between gap-2"><strong>{{ $c->reference }}</strong>
                <span class="{{ $c->status === 'confirmed' ? 'badge-green' : 'badge-warn' }}">{{ $c->status === 'confirmed' ? 'Confirmed' : ucfirst($c->status) }}</span></div>
                @if ($c->status === 'confirmed' && $c->confirmed_start_utc)
                    <p class="mt-1 text-sm">{{ $c->confirmed_start_utc->setTimezone($c->student_timezone)->format('j F Y, H:i') }} ({{ $c->student_timezone }})</p>
                @else
                    <p class="mt-1 text-sm text-muted">Requested for {{ $c->preferred_from->format('j M Y') }}{{ $c->preferred_to ? ' – '.$c->preferred_to->format('j M Y') : '' }}. Not yet confirmed by our team.</p>
                @endif</div>
        @empty
            <p class="card mt-3 p-6 text-sm text-muted">No consultation requests.</p>
        @endforelse
    </section>
</x-layouts.student>
