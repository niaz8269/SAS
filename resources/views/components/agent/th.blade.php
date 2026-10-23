@props(['name', 'sort' => null, 'dir' => 'asc', 'sortable' => true])
{{-- A table heading of the agent backend; sortable ones show the up/down arrows and link to the sorted list. --}}
@php($active = $sortable && $sort === $name)
<th scope="col" aria-sort="{{ $active ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' }}" {{ $attributes->class(['border-b border-[#ddd] bg-[#f5f5f5] px-3 py-3 text-left align-bottom text-[14px] font-bold text-[#555]']) }}>
    @if ($sortable)
        <a class="inline-flex items-center gap-2 hover:text-[#428bb6]" href="{{ request()->fullUrlWithQuery(['sort' => $name, 'dir' => $active && $dir === 'asc' ? 'desc' : 'asc', 'page' => null]) }}">{{ $slot }}<span class="text-xs {{ $active ? 'text-[#428bb6]' : 'text-[#aaa]' }}" aria-hidden="true">{{ $active ? ($dir === 'asc' ? '▲' : '▼') : '⬍' }}</span></a>
    @else
        {{ $slot }}
    @endif
</th>
