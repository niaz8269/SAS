<x-layouts.partner title="Student list">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">Student list</h1>
        <div class="flex flex-wrap gap-2"><a class="btn-primary" href="{{ route('partner.students.create') }}">+ Add students</a><a class="btn-outline" href="{{ route('partner.students.import') }}">+ Batch import</a></div>
    </div>

    <form method="GET" action="{{ route('partner.students.index') }}" class="mt-4 flex flex-wrap items-end gap-2" role="search">
        <div class="min-w-[14rem] flex-1"><label for="q" class="label">Search by name or email</label><input id="q" name="q" type="search" value="{{ $q }}" class="input" maxlength="80"></div>
        <button class="btn-outline">Search</button>
    </form>

    <p class="mt-4 text-sm text-muted" aria-live="polite">{{ $students->total() }} {{ \Illuminate\Support\Str::plural('student', $students->total()) }}</p>
    <div class="card mt-2 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Your students</caption>
            <thead><tr class="border-b border-line text-muted"><th scope="col" class="px-4 py-3">Name</th><th scope="col" class="px-4 py-3">Email</th><th scope="col" class="px-4 py-3">Applications</th><th scope="col" class="px-4 py-3">Account</th><th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            @forelse ($students as $s)
                <tr class="border-b border-line/60 align-top">
                    <td class="px-4 py-3 font-medium">{{ $s->name }}</td>
                    <td class="px-4 py-3">{{ $s->email }}</td>
                    <td class="px-4 py-3">{{ $s->application_plans_count }}</td>
                    <td class="px-4 py-3">{{ $s->email_verified_at ? 'Active' : 'Invitation pending' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap"><a class="text-navy-700 underline" href="{{ route('partner.student.show', $s->public_id) }}">Open</a> &middot; <a class="text-navy-700 underline" href="{{ route('partner.student.edit', $s->public_id) }}">Edit</a> &middot; <a class="text-navy-700 underline" href="{{ route('partner.student.choose.index', $s->public_id) }}">Add application</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-muted">No students yet. Add one to start.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $students->links('pagination::tailwind') }}</div>
</x-layouts.partner>
