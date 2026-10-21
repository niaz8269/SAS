<x-layouts.public title="Scholarships" description="Search officially sourced scholarships by destination, degree, subject, funding and deadline. No account needed.">
    <section class="bg-white">
        <div class="container-page py-8">
            <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <span aria-current="page">Scholarships</span></nav>
            <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">Scholarship directory</h1>
            <p class="mt-2 max-w-3xl text-muted">Every listing links to its official source and shows when it was last checked. This is a curated selection, not every scholarship in the world.</p>
        </div>
    </section>
    <div class="container-page py-8">
        <livewire:scholarship-directory />
    </div>
</x-layouts.public>
