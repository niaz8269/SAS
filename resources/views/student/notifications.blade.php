<x-layouts.student title="Notifications">
    <h1 class="text-2xl font-extrabold">Notifications</h1>

    <section class="card mt-4 p-5" aria-labelledby="prefs"><h2 id="prefs" class="text-lg font-bold">Preferences</h2>
        <p class="mt-1 text-sm text-muted">Deadline reminders are sent 30, 14, 7 and 1 day before a <em>confirmed</em> closing date, for scholarships you saved or are planning. Rolling or unconfirmed deadlines never get dated reminders. Reminders never include documents.</p>
        <form method="POST" action="{{ route('student.notifications.update') }}" class="mt-4">
            @csrf @method('PUT')
            <table class="w-full text-left text-sm"><caption class="sr-only">Notification preferences</caption>
                <thead><tr class="border-b border-line text-muted"><th scope="col" class="py-2 pr-3">Category</th>@foreach (\App\Domain\Communication\Preferences::CHANNELS as $label)<th scope="col" class="py-2 pr-3">{{ $label }}</th>@endforeach</tr></thead>
                <tbody>@foreach (\App\Domain\Communication\Preferences::CATEGORIES as $cat => $catLabel)
                    <tr class="border-b border-line/60"><th scope="row" class="py-3 pr-3 font-medium">{{ $catLabel }}</th>
                    @foreach (\App\Domain\Communication\Preferences::CHANNELS as $ch => $chLabel)
                        <td class="py-3 pr-3"><label class="inline-flex items-center gap-2"><input type="checkbox" name="pref[{{ $cat }}][{{ $ch }}]" value="1" class="rounded border-slate-300 text-navy-700" @checked($preferences[$cat.'.'.$ch]->enabled ?? false)><span class="sr-only">{{ $catLabel }} by {{ $chLabel }}</span></label></td>
                    @endforeach</tr>
                @endforeach</tbody></table>
            <button class="btn-primary mt-4">Save preferences</button>
        </form>
    </section>

    <section class="mt-6" aria-labelledby="inbox"><div class="flex items-center justify-between"><h2 id="inbox" class="text-lg font-bold">Inbox</h2>
        @if ($notifications->whereNull('read_at')->isNotEmpty())<form method="POST" action="{{ route('student.notifications.read-all') }}">@csrf<button class="text-sm text-navy-700 underline">Mark all read</button></form>@endif</div>
        @forelse ($notifications as $n)
            <div class="card mt-3 flex flex-wrap items-start justify-between gap-2 p-4 {{ $n->read_at ? '' : 'border-navy-300 bg-navy-50' }}">
                <div><p class="text-sm">{{ $n->data['message'] ?? 'Notification' }}</p><p class="text-xs text-muted">{{ $n->created_at->diffForHumans() }}</p>
                    @if (! empty($n->data['url']))<a class="text-sm text-navy-700 underline" href="{{ $n->data['url'] }}">View scholarship</a>@endif</div>
                @unless ($n->read_at)<form method="POST" action="{{ route('student.notifications.read', $n->id) }}">@csrf<button class="text-xs underline">Mark read</button></form>@endunless
            </div>
        @empty
            <p class="card mt-3 p-6 text-sm text-muted">No notifications yet.</p>
        @endforelse
    </section>
</x-layouts.student>
