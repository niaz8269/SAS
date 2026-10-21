<x-layouts.public :title="$article->seo_title ?: $article->title" :description="$article->seo_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $article->excerpt), 155)">
    <article class="container-page max-w-3xl py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('resources.index') }}">Resources</a> / <span aria-current="page">{{ $article->title }}</span></nav>
        <p class="eyebrow mt-4">{{ ucfirst($article->category) }}</p>
        <h1 class="mt-1 text-3xl font-extrabold sm:text-4xl">{{ $article->title }}</h1>
        <p class="mt-2 text-sm text-muted">Published {{ $article->published_at->format('j F Y') }}@if($article->author) · {{ $article->author->name }}@endif</p>
        @if ($article->image_path)
            <figure class="mt-6"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public_media')->url($article->image_path) }}" alt="{{ $article->image_alt }}" class="w-full rounded-xl" loading="lazy">
                @if ($article->image_credit)<figcaption class="mt-1 text-xs text-muted">{{ $article->image_credit }}</figcaption>@endif</figure>
        @endif
        <div class="prose-sas mt-6">{!! \App\Support\Html::clean($article->body) !!}</div>
    </article>
</x-layouts.public>
