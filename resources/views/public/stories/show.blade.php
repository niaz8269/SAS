@php
    $canonical = rtrim((string) config('app.url'), '/').'/stories/'.$story->slug;
    $cover = $story->hasPhoto() ? url($story->avatarUrl()) : null;
@endphp
<x-layouts.public :title="$story->name.': '.($story->university ?: 'a student story')" :description="$story->excerpt(155)" :canonical="$canonical" :og_image="$cover">
    <x-slot:head>
        <x-jsonld :data="['@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Student stories', 'item' => route('stories.index')], ['@type' => 'ListItem', 'position' => 3, 'name' => $story->name]]]" />
    </x-slot:head>
    <article class="container-page py-8 sm:py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('stories.index') }}">Student stories</a> / <span aria-current="page">{{ $story->name }}</span></nav>
        <header class="mt-5 flex flex-wrap items-center gap-5">
            <img src="{{ $story->avatarUrl() }}" alt="{{ $story->hasPhoto() ? 'Photo of '.$story->name : '' }}" class="h-28 w-28 rounded-full border border-line object-cover" width="112" height="112">
            <div>
                <h1 class="text-3xl font-extrabold sm:text-4xl">{{ $story->name }}</h1>
                <dl class="mt-2 grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
                    @if ($story->country)<div><dt class="inline text-muted">Country: </dt><dd class="inline font-semibold">{{ $story->country }}</dd></div>@endif
                    @if ($story->university)<div><dt class="inline text-muted">University: </dt><dd class="inline font-semibold">{{ $story->university }}</dd></div>@endif
                    @if ($story->scholarship)<div class="sm:col-span-2"><dt class="inline text-muted">Scholarship or programme: </dt><dd class="inline font-semibold">{{ $story->scholarship }}</dd></div>@endif
                </dl>
                <div class="mt-3"><x-share-button :url="$canonical" :title="$story->name.'\'s story'" /></div>
            </div>
        </header>
        <div class="prose-sas mt-8 max-w-4xl text-lg">{!! \App\Support\Html::rich($story->story) !!}</div>
        <p class="mt-6 text-xs text-muted">This is a student's own account, shared with their permission after our team read it. It is not a promise that you will get the same result.</p>
        <section class="mt-8 rounded-xl bg-navy-900 p-6 text-white" aria-labelledby="story-cta">
            <h2 id="story-cta" class="text-xl font-bold text-white">Your journey could help someone else</h2>
            <p class="mt-1 text-white/85">Did you find a scholarship or study abroad? Tell your story, with your photo if you like.</p>
            <a href="{{ route('stories.create') }}" class="btn-gold btn-lg mt-4">Share your story &rarr;</a>
        </section>
    </article>
    @if ($more->isNotEmpty())
        <section class="container-page pb-12" aria-labelledby="more-stories">
            <h2 id="more-stories" class="text-2xl font-extrabold">More stories</h2>
            <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach ($more as $m)<x-story-card :story="$m" />@endforeach</div>
        </section>
    @endif
</x-layouts.public>
