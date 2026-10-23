<x-layouts.partner title="Submissions">
    <div class="flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-extrabold">Submissions</h1><a class="btn-primary rounded-lg" href="{{ route('partner.submissions.create') }}">Submit a scholarship</a></div>
    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Your submissions</caption>
            <thead><tr class="border-b border-line text-muted"><th scope="col" class="px-4 py-2">Scholarship</th><th scope="col" class="px-4 py-2">Status</th><th scope="col" class="px-4 py-2">Sent</th></tr></thead>
            <tbody>
            @forelse ($submissions as $s)
                <tr class="border-b border-line/60"><td class="px-4 py-3 font-medium"><a class="text-navy-700 underline" href="{{ route('partner.submissions.show', $s) }}">{{ $s->title }}</a></td><td class="px-4 py-3"><span class="badge-neutral">{{ $s->statusLabel() }}</span></td><td class="px-4 py-3 text-muted">{{ $s->created_at->format('j M Y') }}</td></tr>
            @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-muted">No submissions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.partner>