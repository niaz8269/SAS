<x-layouts.student title="Dashboard">
    <h1 class="text-2xl font-extrabold">Welcome, {{ \Illuminate\Support\Str::before($user->name, ' ') }}</h1>
    <p class="mt-1 text-sm text-muted">Your private workspace. Applications are made on each scholarship's official website; here you plan and track them.</p>

    <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('student.saved.index') }}" class="card p-4 hover:border-navy-500"><p class="text-3xl font-extrabold">{{ $savedCount }}</p><p class="text-sm text-muted">Saved scholarships</p></a>
        <a href="{{ route('student.applications.index') }}" class="card p-4 hover:border-navy-500"><p class="text-3xl font-extrabold">{{ $plans->count() }}</p><p class="text-sm text-muted">Application plans</p></a>
        <a href="{{ route('student.documents.index') }}" class="card p-4 hover:border-navy-500"><p class="text-3xl font-extrabold">{{ $pendingScans }}</p><p class="text-sm text-muted">Documents pending scan</p></a>
        <a href="{{ route('student.notifications.index') }}" class="card p-4 hover:border-navy-500"><p class="text-3xl font-extrabold">{{ $unread }}</p><p class="text-sm text-muted">Unread notifications</p></a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card p-5" aria-labelledby="up"><h2 id="up" class="text-lg font-bold">Upcoming confirmed deadlines</h2>
            @forelse ($upcoming as $c)
                <div class="mt-3 border-t border-line pt-3 first:border-0 first:pt-0"><a class="font-semibold hover:underline" href="{{ route('scholarships.show', [$c->programme->slug, $c->slug]) }}">{{ $c->label() }}</a><x-deadline :cycle="$c" class="mt-1" /></div>
            @empty
                <p class="mt-2 text-sm text-muted">No confirmed upcoming deadlines among your saved or planned scholarships.</p>
            @endforelse
            @if ($unconfirmed->isNotEmpty())
                <h3 class="mt-4 text-sm font-semibold">Deadline not confirmed</h3>
                <ul class="mt-1 list-disc pl-5 text-sm text-muted">@foreach ($unconfirmed as $c)<li><a class="underline" href="{{ route('scholarships.show', [$c->programme->slug, $c->slug]) }}">{{ $c->label() }}</a>: check the official page; no reminders are scheduled</li>@endforeach</ul>
            @endif
        </section>

        <section class="card p-5" aria-labelledby="prof"><h2 id="prof" class="text-lg font-bold">Your profile</h2>
            @if (count($missing))
                <p class="mt-2 text-sm">Add these to see how scholarships compare with your details (all optional):</p>
                <ul class="mt-2 list-disc pl-5 text-sm text-muted">@foreach ($missing as $label)<li>{{ $label }}</li>@endforeach</ul>
            @else
                <p class="mt-2 text-sm">Your profile is complete.</p>
            @endif
            <a href="{{ route('student.profile.edit') }}" class="btn-outline mt-3">Edit profile</a>
        </section>

        <section class="card p-5" aria-labelledby="pl"><h2 id="pl" class="text-lg font-bold">Application plans</h2>
            @forelse ($plans->take(5) as $p)
                <a class="mt-3 flex items-center justify-between gap-2 border-t border-line pt-3 first:border-0 first:pt-0 hover:underline" href="{{ route('student.applications.show', $p) }}"><span>{{ $p->cycle->label() }}</span><span class="badge-neutral">{{ $p->status->label() }}</span></a>
            @empty
                <p class="mt-2 text-sm text-muted">No plans yet. Open a scholarship and choose “Track this application”.</p>
            @endforelse
        </section>

        <section class="card p-5" aria-labelledby="en"><h2 id="en" class="text-lg font-bold">Recent enquiries</h2>
            @forelse ($enquiries as $e)
                <a class="mt-3 flex items-center justify-between gap-2 border-t border-line pt-3 first:border-0 first:pt-0 hover:underline" href="{{ route('enquiries.show', $e) }}"><span>{{ $e->reference }}</span><span class="badge-neutral">{{ $e->status->label() }}</span></a>
            @empty
                <p class="mt-2 text-sm text-muted">You have not sent any enquiries.</p>
            @endforelse
            <a href="{{ route('contact') }}" class="btn-outline mt-3">New enquiry</a>
        </section>
    </div>
</x-layouts.student>
