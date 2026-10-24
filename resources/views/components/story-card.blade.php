@props(['story'])
<article class="card group relative flex h-full flex-col p-6 transition hover:-translate-y-0.5 hover:shadow-md">
    <div class="flex items-center gap-4">
        <img src="{{ $story->avatarUrl() }}" alt="{{ $story->hasPhoto() ? 'Photo of '.$story->name : '' }}" class="h-16 w-16 shrink-0 rounded-full border border-line object-cover" width="64" height="64" loading="lazy">
        <div class="min-w-0">
            <p class="font-bold">{{ $story->name }}</p>
            @if ($story->placeLine())<p class="text-sm text-muted">{{ $story->placeLine() }}</p>@endif
            @if ($story->nationality)<p class="text-xs text-muted">Nationality: {{ $story->nationality }}</p>@endif
            @if ($story->scholarship)<p class="mt-0.5 text-xs font-semibold text-navy-700">{{ $story->scholarship }}</p>@endif
        </div>
    </div>
    <blockquote class="mt-4 flex-1 text-sm leading-relaxed">&ldquo;{{ $story->excerpt(240) }}&rdquo;</blockquote>
    @if ($story->hasStory())
        <a href="{{ route('stories.show', $story->slug) }}" class="mt-4 text-sm font-semibold text-navy-700 underline after:absolute after:inset-0 after:content-['']">Read the full story &rarr;</a>
    @endif
</article>
