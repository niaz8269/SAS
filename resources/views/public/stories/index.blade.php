<x-layouts.public title="Student stories" description="Real stories from students who found their scholarship and studied abroad, in their own words.">
    <section class="border-b border-line bg-white">
        <div class="container-page py-8 sm:py-10">
            <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <span aria-current="page">Student stories</span></nav>
            <h1 class="mt-3 text-3xl font-extrabold sm:text-4xl">Student stories</h1>
            <p class="mt-2 text-muted">Real journeys, in the students' own words. Every story is read by our team and published only with the writer's permission.</p>
            <a href="{{ route('stories.create') }}" class="btn-gold btn-lg mt-5">Share your story &rarr;</a>
        </div>
    </section>
    <section class="container-page py-8">
        @if ($stories->isEmpty())
            <div class="card p-8 text-center"><p class="text-lg font-semibold">The first stories will appear here soon.</p><p class="mt-1 text-muted">Have you studied abroad or won a scholarship? Be the first to tell your story.</p><a href="{{ route('stories.create') }}" class="btn-primary mt-4">Share your story</a></div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach ($stories as $s)<x-story-card :story="$s" />@endforeach</div>
            <div class="mt-6">{{ $stories->links('pagination::tailwind') }}</div>
        @endif
    </section>
</x-layouts.public>
