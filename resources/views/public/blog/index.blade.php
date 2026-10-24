@php
    $title = $category ? $category->name.' | Blog' : ($q !== '' ? 'Search the blog' : 'Blog');
    $desc = $category?->description ?: 'News, advice and stories on scholarships, applications, universities and studying abroad.';
@endphp
<x-layouts.public :title="$title" :description="$desc" :noindex="$q !== ''">
    <x-slot:head>
        <link rel="alternate" type="application/rss+xml" title="{{ config('sas.brand.short') }} blog" href="{{ route('blog.feed') }}">
    </x-slot:head>
    <section class="border-b border-line bg-white">
        <div class="container-page py-8 sm:py-10">
            <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / @if ($category)<a class="hover:underline" href="{{ route('blog.index') }}">Blog</a> / <span aria-current="page">{{ $category->name }}</span>@else<span aria-current="page">Blog</span>@endif</nav>
            <h1 class="mt-3 text-3xl font-extrabold sm:text-4xl">{{ $category ? $category->name : 'The SAS blog' }}</h1>
            <p class="mt-2 text-muted">{{ $category?->description ?: 'News, advice and stories on scholarships, applications, universities and studying abroad.' }}</p>
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <form method="GET" action="{{ $category ? route('blog.category', $category->slug) : route('blog.index') }}" class="flex w-full max-w-md gap-2" role="search">
                    <label for="blog-q" class="sr-only">Search the blog</label>
                    <input id="blog-q" name="q" value="{{ $q }}" maxlength="80" placeholder="Search the blog" class="input">
                    <button class="btn-primary">Search</button>
                </form>
                @if ($categories->isNotEmpty())
                    <nav aria-label="Blog categories" class="flex flex-wrap gap-2">
                        <a href="{{ route('blog.index') }}" class="badge {{ ! $category ? 'bg-navy-700 text-white' : 'bg-navy-50 text-navy-800 hover:bg-navy-100' }} !px-3 !py-1" @if(! $category) aria-current="page" @endif>All</a>
                        @foreach ($categories as $c)
                            <a href="{{ route('blog.category', $c->slug) }}" class="badge {{ $category?->id === $c->id ? 'bg-navy-700 text-white' : 'bg-navy-50 text-navy-800 hover:bg-navy-100' }} !px-3 !py-1" @if($category?->id === $c->id) aria-current="page" @endif>{{ $c->name }} <span class="opacity-70">{{ $c->live_posts_count }}</span></a>
                        @endforeach
                    </nav>
                @endif
            </div>
        </div>
    </section>
    <section class="container-page py-8">
        @if ($q !== '')<p class="mb-4 text-sm text-muted">{{ $posts->total() }} {{ \Illuminate\Support\Str::plural('post', $posts->total()) }} for &ldquo;{{ $q }}&rdquo;. <a class="underline" href="{{ $category ? route('blog.category', $category->slug) : route('blog.index') }}">Clear search</a></p>@endif
        @if ($featured)<div class="mb-6"><x-blog-card :post="$featured" :large="true" /></div>@endif
        @if ($posts->isEmpty() && ! $featured)
            <p class="card p-6 text-sm text-muted">{{ $q !== '' ? 'No posts match your search.' : 'Posts will appear here once they are published.' }}</p>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)<x-blog-card :post="$post" />@endforeach
            </div>
            <div class="mt-6">{{ $posts->links('pagination::tailwind') }}</div>
        @endif
    </section>
</x-layouts.public>
