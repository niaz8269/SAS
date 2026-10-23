<x-layouts.student title="My applications">
    <p class="text-sm font-semibold uppercase tracking-wide text-navy-700">Step 3 of 3</p>
    <h1 class="text-2xl font-extrabold">My applications</h1>
    <p class="mt-1 text-sm text-muted">These are the scholarships you chose. Open one to see the documents it needs and upload them.</p>
    @forelse ($plans as $p)
        @php($d = $docs[$p->id])
        <a href="{{ route('student.applications.show', $p) }}" class="card mt-4 block p-5 hover:border-navy-500">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-bold">{{ $p->cycle->displayName() }}</h2>
                @if ($p->review_requested_at)<span class="badge-green">Sent to SAS for review</span>
                @elseif ($d['required'] > 0 && $d['ready'] === $d['required'])<span class="badge-green">Documents ready</span>
                @else<span class="badge-warn">Documents needed</span>@endif
            </div>
            @if ($d['required'] > 0)
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-ivory-deep" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $d['required'] }}" aria-valuenow="{{ $d['ready'] }}" aria-label="Mandatory documents provided"><div class="h-full bg-navy-600" style="width: {{ round($d['ready'] / $d['required'] * 100) }}%"></div></div>
                <p class="mt-1 text-sm text-muted">{{ $d['ready'] }} of {{ $d['required'] }} mandatory documents provided</p>
            @else
                <p class="mt-2 text-sm text-muted">No mandatory documents.</p>
            @endif
            @if ($supported->has($p->cycle_id))<p class="mt-1 text-xs"><strong>SAS is helping you with this: {{ $supported[$p->cycle_id]->statusLabel() }}</strong></p>@endif
        </a>
    @empty
        <div class="card mt-4 p-8 text-center"><p class="font-semibold">You have not chosen a scholarship yet</p><p class="mt-1 text-sm text-muted">Go to step 2 and press Apply next to the scholarships you want.</p><a href="{{ route('student.choose.index') }}" class="btn-primary mt-4">Choose scholarships</a></div>
    @endforelse
    @if ($plans->isNotEmpty())<p class="mt-5"><a class="btn-outline" href="{{ route('student.choose.index') }}">Choose another scholarship</a></p>@endif
</x-layouts.student>
