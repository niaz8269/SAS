@props(['post', 'large' => false])
<article class="card flex h-full flex-col overflow-hidden {{ $large ? 'lg:grid lg:grid-cols-2' : '' }}">
    <a href="{{ route('blog.show', $post->slug) }}" class="block bg-navy-50" tabindex="-1" aria-hidden="true">
        @if ($post->imageUrl())
            <img src="{{ $post->imageUrl() }}" alt="" class="{{ $large ? 'h-full min-h-[14rem]' : 'aspect-[16/9]' }} w-full object-cover" loading="lazy" width="800" height="450">
        @else
            <div class="{{ $large ? 'h-full min-h-[14rem]' : 'aspect-[16/9]' }} flex w-full items-center justify-center bg-gradient-to-br from-navy-800 to-navy-600 text-white/80"><img src="{{ asset('images/sas-seal.png') }}" alt="" class="h-16 w-16 opacity-80" loading="lazy" width="64" height="64"></div>
        @endif
    </a>
    <div class="flex flex-1 flex-col p-5">
        <p class="eyebrow">@if ($post->category)<a class="hover:underline" href="{{ route('blog.category', $post->category->slug) }}">{{ $post->category->name }}</a>@else Blog @endif</p>
        <h2 class="{{ $large ? 'text-2xl' : 'text-lg' }} mt-1 font-bold"><a class="hover:underline" href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a></h2>
        <p class="mt-2 text-sm text-muted">{{ $post->summary($large ? 260 : 150) }}</p>
        <p class="mt-auto pt-4 text-xs text-muted">{{ $post->published_at?->format('j M Y') }} &middot; {{ $post->readingMinutes() }} min read</p>
    </div>
</article>
