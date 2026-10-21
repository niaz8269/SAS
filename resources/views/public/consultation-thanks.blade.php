<x-layouts.public title="Consultation requested" :noindex="true">
    <div class="container-page max-w-2xl py-12"><div class="card p-8" role="status">
        <h1 class="text-2xl font-extrabold">Consultation requested</h1>
        <p class="mt-3">Your reference is <strong class="rounded bg-lime-soft px-2 py-0.5">{{ $reference }}</strong>.</p>
        <p class="mt-3 text-sm text-muted">This is a request, not a confirmed booking. A member of the team will contact you to confirm a specific date and time.</p>
        <a href="{{ route('home') }}" class="btn-outline mt-6">Back to the homepage</a>
    </div></div>
</x-layouts.public>
