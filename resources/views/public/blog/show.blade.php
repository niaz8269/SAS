@php
    $cover = $post->imageUrl() ? url($post->imageUrl()) : null;
    $canonical = rtrim((string) config('app.url'), '/').'/blog/'.$post->slug;
@endphp
<x-layouts.public :title="$post->seo_title ?: $post->title" :description="$post->seo_description ?: $post->summary(155)" :og_image="$cover" :canonical="$canonical" :noindex="$preview">
    <x-slot:head>
        <link rel="alternate" type="application/rss+xml" title="{{ config('sas.brand.short') }} blog" href="{{ route('blog.feed') }}">
        @unless ($preview)
            <x-jsonld :data="array_filter(['@type' => 'BlogPosting', 'headline' => $post->title, 'description' => $post->summary(200), 'image' => $cover, 'datePublished' => $post->published_at?->toIso8601String(), 'dateModified' => $post->updated_at?->toIso8601String(), 'mainEntityOfPage' => $canonical, 'articleSection' => $post->category?->name, 'author' => ['@type' => 'Person', 'name' => $post->author?->name ?: config('sas.brand.name')], 'publisher' => ['@type' => 'Organization', 'name' => config('sas.brand.name'), 'logo' => ['@type' => 'ImageObject', 'url' => asset('images/sas-seal.png')]]])" />
            <x-jsonld :data="['@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => route('blog.index')], ['@type' => 'ListItem', 'position' => 3, 'name' => $post->title]]]" />
        @endunless
    </x-slot:head>
    @if ($preview)
        <div class="bg-warn-soft px-4 py-2 text-center text-sm text-warn" role="note"><strong>Preview.</strong> {{ $post->isScheduled() ? 'This post is scheduled for '.$post->published_at->format('j M Y, H:i').'.' : 'This post is a draft.' }} Only staff can see this page.</div>
    @endif
    <div class="container-page grid gap-8 py-8 sm:py-10 lg:grid-cols-[minmax(0,1fr)_20rem]">
    <article class="min-w-0">
        <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('blog.index') }}">Blog</a> / <span aria-current="page">{{ \Illuminate\Support\Str::limit($post->title, 60) }}</span></nav>
        <header class="mt-4">
            <p class="eyebrow">@if ($post->category)<a class="hover:underline" href="{{ route('blog.category', $post->category->slug) }}">{{ $post->category->name }}</a>@else Blog @endif</p>
            <h1 class="mt-1 text-3xl font-extrabold sm:text-4xl lg:text-5xl">{{ $post->title }}</h1>
            @if ($post->excerpt)<p class="mt-3 text-lg text-muted">{{ \App\Support\Html::text($post->excerpt) }}</p>@endif
            <p class="mt-3 text-sm text-muted">
                @if ($post->author)By <strong class="text-ink">{{ $post->author->name }}</strong> &middot; @endif
                <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('j F Y') ?? 'Not published' }}</time> &middot; {{ $post->readingMinutes() }} min read
            </p>
        </header>
        @if ($post->imageUrl())
            <figure class="mt-6"><img src="{{ $post->imageUrl() }}" alt="{{ $post->image_alt }}" class="max-h-[32rem] w-full rounded-xl object-cover" width="1200" height="630" fetchpriority="high">
                @if ($post->image_credit)<figcaption class="mt-1 text-xs text-muted">{{ $post->image_credit }}</figcaption>@endif</figure>
        @endif
        <div class="prose-sas mt-6">{!! \App\Support\Html::rich($post->body) !!}</div>

        <aside class="mt-8 flex flex-wrap items-center gap-3 border-t border-line pt-5 text-sm" aria-label="Share this post">
            <span class="font-semibold">Share:</span>
            <a class="btn-outline !py-1.5" rel="noopener" target="_blank" href="https://wa.me/?text={{ rawurlencode($post->title.' '.$canonical) }}">WhatsApp</a>
            <a class="btn-outline !py-1.5" rel="noopener" target="_blank" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($canonical) }}">Facebook</a>
            <a class="btn-outline !py-1.5" rel="noopener" target="_blank" href="https://twitter.com/intent/tweet?url={{ rawurlencode($canonical) }}&text={{ rawurlencode($post->title) }}">X</a>
            <a class="btn-outline !py-1.5" rel="noopener" target="_blank" href="https://www.linkedin.com/sharing/share-offsite/?url={{ rawurlencode($canonical) }}">LinkedIn</a>
        </aside>

    </article>
    <aside class="space-y-5 lg:sticky lg:top-28 lg:self-start" aria-label="Blog sidebar">
        <section class="rounded-xl bg-navy-900 p-5 text-white" aria-labelledby="blog-cta">
            <h2 id="blog-cta" class="text-lg font-bold text-white">Want help with your own application?</h2>
            <p class="mt-1 text-sm text-white/85">Book a free consultation and a counsellor will go through scholarships that fit you.</p>
            <a href="{{ route('consultations.create') }}" class="btn-gold mt-4">Book a consultation &rarr;</a>
        </section>
        @if ($categories->isNotEmpty())
            <section class="card p-5" aria-labelledby="side-cats">
                <h2 id="side-cats" class="text-base font-bold">Categories</h2>
                <ul class="mt-2 space-y-1 text-sm">@foreach ($categories as $c)<li><a class="hover:underline" href="{{ route('blog.category', $c->slug) }}">{{ $c->name }}</a></li>@endforeach</ul>
            </section>
        @endif
        @if ($recent->isNotEmpty())
            <section class="card p-5" aria-labelledby="side-recent">
                <h2 id="side-recent" class="text-base font-bold">Latest posts</h2>
                <ul class="mt-2 space-y-3 text-sm">@foreach ($recent as $r)<li><a class="font-medium hover:underline" href="{{ route('blog.show', $r->slug) }}">{{ $r->title }}</a><br><span class="text-xs text-muted">{{ $r->published_at->format('j M Y') }}</span></li>@endforeach</ul>
                <a class="mt-3 inline-block text-sm font-semibold text-navy-700 underline" href="{{ route('blog.index') }}">All posts &rarr;</a>
            </section>
        @endif
    </aside>
    </div>
    @if ($related->isNotEmpty())
        <section class="container-page pb-12" aria-labelledby="more-posts">
            <h2 id="more-posts" class="text-2xl font-extrabold">More from the blog</h2>
            <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach ($related as $r)<x-blog-card :post="$r" />@endforeach</div>
        </section>
    @endif
</x-layouts.public>
