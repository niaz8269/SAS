<x-layouts.student title="Saved scholarships">
    <h1 class="text-2xl font-extrabold">Saved scholarships</h1>
    @forelse ($saved as $s)
        @if ($loop->first)<div class="mt-4 grid gap-4 md:grid-cols-2">@endif
        <div>
            <x-cycle-card :cycle="$s->cycle" />
            <form method="POST" action="{{ route('student.saved.destroy', $s->cycle) }}" class="mt-2">@csrf @method('DELETE')<button class="btn-ghost !py-1.5 text-sm">Remove from saved</button></form>
        </div>
        @if ($loop->last)</div>@endif
    @empty
        <div class="card mt-4 p-8 text-center"><p class="font-semibold">Nothing saved yet</p><p class="mt-1 text-sm text-muted">Save scholarships to compare them and get deadline reminders you choose.</p><a href="{{ route('scholarships.index') }}" class="btn-primary mt-4">Browse scholarships</a></div>
    @endforelse
</x-layouts.student>
