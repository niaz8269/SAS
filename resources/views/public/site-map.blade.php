<x-layouts.public title="Site map: every page" :description="'Every scholarship, university, study destination, guide and blog post on '.config('sas.brand.short').', on one page.'" :crumbs="[['Home', route('home')], ['Site map']]">
    <div class="container-page py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <span aria-current="page">Site map</span></nav>
        <h1 class="mt-3 text-3xl font-extrabold sm:text-4xl">Site map</h1>
        <p class="mt-3 max-w-2xl text-muted">Every page of the website, grouped by kind. New scholarships, universities, guides and posts appear here as soon as the team publishes them.</p>
        <nav aria-label="Jump to" class="mt-4 flex flex-wrap gap-2 text-sm">
            @foreach ($sections as $key => $section)
                <a class="rounded-full border border-line px-3 py-1 hover:bg-navy-50" href="#{{ $key }}">{{ $section['heading'] }} ({{ number_format($section['entries']->count()) }})</a>
            @endforeach
        </nav>
        @foreach ($sections as $key => $section)
            <section id="{{ $key }}" class="mt-10">
                <h2 class="text-xl font-bold">{{ $section['heading'] }}</h2>
                <ul class="mt-3 grid gap-x-8 gap-y-1.5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($section['entries'] as [$url, , $label])
                        <li class="min-w-0 break-words"><a class="text-navy-700 hover:underline" href="{{ $url }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</x-layouts.public>
