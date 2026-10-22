@php $intro = \App\Models\PageBlock::builtIn('destinations', 'intro'); $emptyMsg = \App\Models\PageBlock::builtIn('destinations', 'empty'); @endphp
<x-layouts.public title="Study destinations" :description="$intro?->subheading ?: 'Study destination guides with costs, official sources and the scholarships available there.'">
    <x-page-blocks page="destinations" position="top" />
    <section class="container-page py-10">
        <h1 class="text-3xl font-extrabold sm:text-4xl">{{ $intro?->heading ?: 'Study destinations' }}</h1>
        <p class="mt-2 max-w-3xl text-muted">{{ $intro?->body ?: 'Each guide shows its sources and the date it was last reviewed. Visa and entry rules change: always confirm with the official authority.' }}</p>
        @if ($countries->isEmpty())
            <p class="card mt-6 p-6 text-sm text-muted">{{ $emptyMsg?->body ?: 'Destination guides will appear here once they have been reviewed and published.' }} In the meantime you can <a class="text-forest-700 underline" href="{{ route('scholarships.index') }}">browse scholarships</a>.</p>
        @else
            <ul class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($countries as $c)
                    <li><a href="{{ route('destinations.show', $c) }}" class="group relative flex h-48 items-end overflow-hidden rounded-xl bg-gradient-to-br from-forest-700 to-forest-900 p-5 text-white">
                        <span class="absolute inset-0 opacity-25" style="background: radial-gradient(circle at 85% 15%, #cfe86a, transparent 55%)" aria-hidden="true"></span>
                        <span class="relative"><span class="block text-xl font-bold group-hover:underline">{{ $c->name }}</span><span class="text-sm text-forest-100">{{ \Illuminate\Support\Str::limit($c->summary, 70) }}</span></span></a></li>
                @endforeach
            </ul>
        @endif
    </section>
    <x-page-blocks page="destinations" position="bottom" />
</x-layouts.public>
