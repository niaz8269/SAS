@props(['paginator', 'searchLabel' => 'Name/Email/Passport', 'q' => null, 'perPage' => 25])
{{-- Rows per page, search, the "1-25 out of 26" line and the page buttons of the agent backend. Keeps the other filters in the URL. --}}
@php
    $keep = collect(request()->query())->except(['page', 'per_page', 'q'])->all();
    $first = $paginator->total() ? $paginator->firstItem() : 0;
    $btn = 'border border-[#ddd] bg-white px-4 py-2 text-[#428bb6] hover:bg-[#f0f6fa]';
@endphp
<div class="px-4 py-4">
    <form method="GET" class="flex flex-wrap items-center justify-between gap-3" role="search">
        @foreach ($keep as $k => $v)@if (is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
        <label class="flex items-center gap-2 text-[16px]"><select name="per_page" class="border border-[#ccc] bg-white px-2 py-1.5" onchange="this.form.submit()" aria-label="Rows per page">@foreach ([10, 25, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach</select> Each page</label>
        <label class="flex items-center gap-2 text-[16px]">search:
            <input name="q" type="search" value="{{ $q }}" maxlength="80" placeholder="{{ $searchLabel }}" class="w-60 max-w-full border border-[#ccc] px-3 py-1.5"></label>
    </form>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-[16px]">
        <p aria-live="polite">{{ $first }}-{{ $paginator->total() ? $paginator->lastItem() : 0 }} out of {{ $paginator->total() }}</p>
        <nav aria-label="Pages" class="flex flex-wrap items-stretch text-[16px]">
            @if ($paginator->onFirstPage())<span class="{{ $btn }} text-[#999]">Homepage</span><span class="{{ $btn }} text-[#999]">| Previous Page</span>
            @else<a class="{{ $btn }}" href="{{ $paginator->url(1) }}">Homepage</a><a class="{{ $btn }}" href="{{ $paginator->previousPageUrl() }}">| Previous Page</a>@endif
            <form method="GET" class="flex items-center border border-[#ddd] bg-white px-3 text-[#428bb6]">
                @foreach ($keep + ['per_page' => $perPage, 'q' => $q] as $k => $v)@if (is_scalar($v) && $v !== null)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
                <label for="jump" class="mr-2">| Jump to</label><input id="jump" name="page" type="number" min="1" max="{{ $paginator->lastPage() }}" value="{{ $paginator->currentPage() }}" class="w-12 border border-[#ccc] px-1 py-1 text-center text-[#333]">
            </form>
            <span class="{{ $btn }} text-[#333]">Page {{ $paginator->currentPage() }} of {{ max($paginator->lastPage(), 1) }}</span>
            @if ($paginator->hasMorePages())<a class="{{ $btn }}" href="{{ $paginator->nextPageUrl() }}">Next page</a><a class="{{ $btn }}" href="{{ $paginator->url($paginator->lastPage()) }}">Last page</a>
            @else<span class="{{ $btn }} text-[#999]">Next page</span><span class="{{ $btn }} text-[#999]">Last page</span>@endif
        </nav>
    </div>
</div>
