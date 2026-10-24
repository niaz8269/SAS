@props(['data'])
@php
    // The key is built from two parts because Blade would read a written-out "at context" as one of its own directives.
    $ld = ['@'.'context' => 'https://schema.org'] + $data;
@endphp
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
