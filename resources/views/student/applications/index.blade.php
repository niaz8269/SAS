<x-layouts.student title="Application plans">
    <h1 class="text-2xl font-extrabold">Application plans</h1>
    <p class="mt-1 text-sm text-muted">A plan is your private checklist. A status of “Submitted” is what you report; SAS does not submit applications.</p>
    @forelse ($plans as $p)
        @php($pr = $service->progress($p))
        <a href="{{ route('student.applications.show', $p) }}" class="card mt-4 block p-5 hover:border-forest-500">
            <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-bold">{{ $p->cycle->label() }}</h2><span class="badge-neutral">{{ $p->status->label() }}{{ $p->status === \App\Enums\PlanStatus::Submitted ? ' · reported by you' : '' }}</span></div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-ivory-deep" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pr['percent'] }}" aria-label="Checklist progress"><div class="h-full bg-forest-600" style="width: {{ $pr['percent'] }}%"></div></div>
            <p class="mt-1 text-xs text-muted">{{ $pr['done'] }} of {{ $pr['total'] }} tasks done</p>
        </a>
    @empty
        <div class="card mt-4 p-8 text-center"><p class="font-semibold">No application plans yet</p><p class="mt-1 text-sm text-muted">Open a scholarship and choose “Track this application”.</p><a href="{{ route('scholarships.index') }}" class="btn-primary mt-4">Browse scholarships</a></div>
    @endforelse
</x-layouts.student>
