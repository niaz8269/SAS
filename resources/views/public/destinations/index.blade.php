@php $intro = \App\Models\PageBlock::builtIn('destinations', 'intro'); $emptyMsg = \App\Models\PageBlock::builtIn('destinations', 'empty'); @endphp
<x-layouts.public title="Study destinations" :description="$intro?->subheading ?: 'Study destination guides with costs, official sources and the scholarships available there.'">
    <x-page-blocks page="destinations" position="top" />
    <section class="container-page py-10">
        <h1 class="text-3xl font-extrabold sm:text-4xl">{{ $intro?->heading ?: 'Study destinations' }}</h1>
        <p class="mt-2 text-muted">{{ $intro?->body ?: 'Each guide shows its sources and the date it was last reviewed. Visa and entry rules change: always confirm with the official authority.' }}</p>
        @if ($countries->isEmpty())
            <p class="card mt-6 p-6 text-sm text-muted">{{ $emptyMsg?->body ?: 'Destination guides will appear here once they have been reviewed and published.' }} In the meantime you can <a class="text-navy-700 underline" href="{{ route('scholarships.index') }}">browse scholarships</a>.</p>
        @else
            <ul class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($countries as $c)
                    <li><x-destination-card :country="$c" /></li>
                @endforeach
            </ul>
        @endif
    </section>
    <x-page-blocks page="destinations" position="bottom" />
</x-layouts.public>
