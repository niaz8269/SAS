<x-layouts.partner :title="$student->name">
    <p class="text-sm"><a class="text-navy-700 underline" href="{{ route('partner.students.index') }}">&larr; Student list</a></p>
    <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
        <div><h1 class="text-2xl font-extrabold">{{ $student->name }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $student->email }} &middot; {{ $student->email_verified_at ? 'Account active' : 'Invitation pending' }} &middot; details {{ $formPercent }}% filled in</p></div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-primary" href="{{ route('partner.student.choose.index', $student->public_id) }}">Add application</a>
            <a class="btn-outline" href="{{ route('partner.student.form.show', $student->public_id) }}">Application form</a>
            <a class="btn-outline" href="{{ route('partner.student.edit', $student->public_id) }}">Edit</a>
        </div>
    </div>

    @unless ($student->email_verified_at)
        <form method="POST" action="{{ route('partner.student.invite', $student->public_id) }}" class="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-line bg-white p-4 text-sm">@csrf
            <p class="flex-1">The student has not chosen a password yet.</p><button class="btn-outline !py-1.5">Send the invitation again</button></form>
    @endunless

    <section class="mt-6" aria-labelledby="apps-h"><h2 id="apps-h" class="text-lg font-bold">Applications</h2>
        @forelse ($plans as $p)
            <div class="card mt-3 flex flex-wrap items-center justify-between gap-3 p-4">
                <div><p class="font-semibold">{{ $p->cycle->displayName() }}</p><p class="text-sm text-muted">{{ $p->application_no }} &middot; {{ $p->stageLabel() }}</p></div>
                <a class="btn-outline !py-1.5" href="{{ route('partner.student.applications.show', [$student->public_id, $p->public_id]) }}">Open</a>
            </div>
        @empty
            <p class="card mt-3 p-6 text-sm text-muted">No applications yet. Press Add application to choose a scholarship.</p>
        @endforelse
    </section>
</x-layouts.partner>
