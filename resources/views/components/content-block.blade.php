@props(['block'])
@php
    $href = fn ($u) => \App\Domain\Site\Site::href($u);
    $img = $block->image_path ? \Illuminate\Support\Facades\Storage::disk('public_media')->url($block->image_path) : null;
@endphp
<section class="container-page py-10" aria-labelledby="blk-{{ $block->getTable() }}-{{ $block->id }}">
    <div class="grid items-center gap-8 {{ $img ? 'lg:grid-cols-2' : '' }}">
        <div>
            @if ($block->eyebrow)<p class="eyebrow">{{ $block->eyebrow }}</p>@endif
            <h2 id="blk-{{ $block->getTable() }}-{{ $block->id }}" class="section-title mt-1">{{ $block->heading }}</h2>
            @if ($block->subheading)<p class="mt-1 text-muted">{{ $block->subheading }}</p>@endif
            @if ($block->body)<p class="mt-3 whitespace-pre-line">{{ $block->body }}</p>@endif
            @if ($block->cta_label || $block->cta2_label)
                <div class="mt-5 flex flex-wrap gap-3">
                    @if ($block->cta_label)<a href="{{ $href($block->cta_url) }}" class="btn-primary">{{ $block->cta_label }}</a>@endif
                    @if ($block->cta2_label)<a href="{{ $href($block->cta2_url) }}" class="btn-outline">{{ $block->cta2_label }}</a>@endif
                </div>
            @endif
        </div>
        @if ($img)<img src="{{ $img }}" alt="" class="w-full rounded-2xl object-cover" loading="lazy">@endif
    </div>
    @if (! empty($block->items))
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($block->items as $item)
                <li class="card p-5"><h3 class="font-bold">{{ $item['title'] ?? '' }}</h3><p class="mt-1 text-sm text-muted">{{ $item['text'] ?? '' }}</p></li>
            @endforeach
        </ul>
    @endif
</section>
