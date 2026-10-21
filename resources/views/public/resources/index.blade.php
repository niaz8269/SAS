<x-layouts.public title="Resources" description="Guides and articles on scholarships, applications and studying abroad.">
    <section class="container-page py-10">
        <h1 class="text-3xl font-extrabold sm:text-4xl">Resources</h1>
        <p class="mt-2 max-w-3xl text-muted">Practical guides for international students.</p>
        @if ($articles->isEmpty())
            <p class="card mt-6 p-6 text-sm text-muted">Guides will appear here once they are published.</p>
        @else
            <div class="mt-6 grid gap-5 md:grid-cols-3">
                @foreach ($articles as $a)
                    <article class="card p-5"><p class="eyebrow">{{ ucfirst($a->category) }}</p>
                        <h2 class="mt-1 text-lg font-bold"><a class="hover:underline" href="{{ route('resources.show', $a) }}">{{ $a->title }}</a></h2>
                        <p class="mt-2 text-sm text-muted">{{ $a->excerpt }}</p><p class="mt-3 text-xs text-muted">{{ $a->published_at->format('j M Y') }}</p></article>
                @endforeach
            </div>
            <div class="mt-6">{{ $articles->links() }}</div>
        @endif
    </section>
</x-layouts.public>
