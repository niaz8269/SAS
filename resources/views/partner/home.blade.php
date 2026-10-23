<x-layouts.partner title="Partner area">
    <h1 class="text-2xl font-extrabold">{{ $organisation->name }}</h1>
    <p class="mt-1 text-sm text-muted">{{ \App\Models\PartnerOrganisation::KINDS[$organisation->kind] ?? $organisation->kind }}@if ($organisation->country) &middot; {{ $organisation->country->name }}@endif</p>

    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (['submitted' => 'Waiting for editors', 'in_review' => 'Being checked', 'needs_changes' => 'Needs your changes', 'accepted' => 'Accepted'] as $key => $label)
            <a href="{{ route('partner.submissions.index') }}" class="card block p-4 transition hover:border-navy-500"><p class="text-2xl font-extrabold">{{ $counts[$key] ?? 0 }}</p><p class="text-sm text-muted">{{ $label }}</p></a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <section class="card p-5" aria-labelledby="recent-h">
            <div class="flex flex-wrap items-center justify-between gap-2"><h2 id="recent-h" class="font-bold">Your latest submissions</h2><a class="btn-primary rounded-lg" href="{{ route('partner.submissions.create') }}">Submit a scholarship</a></div>
            @forelse ($submissions->take(5) as $s)
                <a href="{{ route('partner.submissions.show', $s) }}" class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-line p-3 text-sm transition hover:border-navy-500"><span class="font-medium">{{ $s->title }}</span><span class="badge-neutral">{{ $s->statusLabel() }}</span></a>
            @empty
                <p class="mt-3 text-sm text-muted">Nothing submitted yet. Send us a scholarship, its official page and the key details, and our editors will check and publish it.</p>
            @endforelse
        </section>
        <section class="card p-5 text-sm" aria-labelledby="how-h">
            <h2 id="how-h" class="font-bold">How it works</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-muted">
                <li>Submit the scholarship with the official page that states its details.</li>
                <li>Our editors check it against that page. They may ask you for changes here.</li>
                <li>Accepted listings go through our normal review before they are published. You can follow every step here.</li>
            </ol>
            <p class="mt-4 text-muted">Partners never see student profiles or documents.</p>
        </section>
    </div>
</x-layouts.partner>