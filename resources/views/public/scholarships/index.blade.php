@php $intro = \App\Models\PageBlock::builtIn('directory', 'intro'); @endphp
<x-layouts.public title="Scholarships" :description="$intro?->subheading ?: 'Search officially sourced scholarships by destination, degree, subject, funding and deadline. No account needed.'">
    <section class="bg-white">
        <div class="container-page py-8">
            <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <span aria-current="page">Scholarships</span></nav>
            <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">{{ $intro?->heading ?: 'Scholarship directory' }}</h1>
            <p class="mt-2 max-w-3xl text-muted">{{ $intro?->body ?: 'Every listing links to its official source and shows when it was last checked. This is a curated selection, not every scholarship in the world.' }}</p>
        </div>
    </section>
    <x-page-blocks page="directory" position="top" />
    <div class="container-page py-8">
        <livewire:scholarship-directory />
    </div>
    <x-page-blocks page="directory" position="bottom" />
</x-layouts.public>