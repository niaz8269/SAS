<x-layouts.student title="Book a call">
    <h1 class="text-2xl font-extrabold">Book a call with your counsellor</h1>
    <p class="mt-1 text-sm text-muted">@if ($counsellor) Free times of <strong>{{ $counsellor->name }}</strong>. @else You do not have a counsellor yet, so you see the free times of the whole team. @endif Times are shown in your time zone ({{ $tz }}); change it under Profile.</p>

    @if ($mine->isNotEmpty())
        <section class="card mt-5 p-4" aria-labelledby="mine-h">
            <h2 id="mine-h" class="text-lg font-bold">Your booked call</h2>
            @foreach ($mine as $s)
                <div class="mt-2 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <p><strong>{{ $s->starts_at->copy()->timezone($tz)->format('l j F, H:i') }}</strong> for {{ $s->duration_minutes }} minutes with {{ $s->counsellor->name }}</p>
                    <form method="POST" action="{{ route('student.book.destroy', $s) }}" onsubmit="return confirm('Cancel this call?')">@csrf @method('DELETE')<button class="btn-outline !py-1.5">Cancel</button></form>
                </div>
            @endforeach
            <p class="mt-2 text-xs text-muted">You can cancel up to 24 hours before. After that, write to your counsellor.</p>
        </section>
    @endif

    <section class="mt-5" aria-labelledby="free-h">
        <h2 id="free-h" class="text-lg font-bold">Free times</h2>
        @forelse ($days as $day => $slots)
            <div class="card mt-3 p-4">
                <h3 class="font-semibold">{{ \Illuminate\Support\Carbon::parse($day)->format('l j F') }}</h3>
                <form method="POST" action="{{ route('student.book.store') }}" class="mt-2 space-y-2">@csrf
                    <div class="flex flex-wrap gap-3">
                        @foreach ($slots as $s)
                            <label class="flex items-center gap-2 rounded border border-line px-3 py-2 text-sm"><input type="radio" name="slot" value="{{ $s->id }}" required> {{ $s->starts_at->copy()->timezone($tz)->format('H:i') }} <span class="text-muted">({{ $s->duration_minutes }} min{{ $counsellor ? '' : ', '.$s->counsellor->name }})</span></label>
                        @endforeach
                    </div>
                    <div><label for="note-{{ $day }}" class="label">What would you like to talk about? (optional)</label><input id="note-{{ $day }}" name="note" maxlength="500" class="input"></div>
                    <button class="btn-primary">Book this time</button>
                </form>
            </div>
        @empty
            <p class="card mt-3 p-5 text-sm text-muted">No free times in the next three weeks. Write to your counsellor under My counsellor and ask for a time.</p>
        @endforelse
    </section>
</x-layouts.student>