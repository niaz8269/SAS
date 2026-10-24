@props(['title' => 'List'])
{{-- The blue "List" panel of the agent backend: a blue bar with actions on the right, and the table inside. --}}
<section {{ $attributes->class(['mt-5 border border-[#2f7cc4]']) }} aria-label="{{ $title }}">
    <div class="flex flex-wrap items-center justify-between gap-3 bg-[#307cc7] px-4 py-3 text-white">
        <h2 class="text-[0.875rem]">{{ $title }}</h2>
        @isset($actions)<div class="flex flex-wrap items-center gap-5 text-[0.875rem]">{{ $actions }}</div>@endisset
    </div>
    <div class="bg-white">{{ $slot }}</div>
</section>
