@props(['page', 'position' => 'top'])
@foreach (\App\Models\PageBlock::added($page, $position) as $block)
    <x-content-block :block="$block" />
@endforeach
