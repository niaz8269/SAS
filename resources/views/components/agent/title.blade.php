{{-- A page title in the agent backend: large light blue text with a thin rule, and optional action buttons on the right. --}}
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e5e5e5] pb-3">
    <h1 class="text-[24px] font-light leading-tight text-[#3a87ad]">{{ $slot }}</h1>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</div>
