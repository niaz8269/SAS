{{-- Browser tab icon: the one staff picked under Site settings, otherwise the bundled SAS icon. --}}
@php
    $favicon = \App\Domain\Site\Site::mediaUrl('favicon_path');
@endphp
@if ($favicon)
    <link rel="icon" href="{{ $favicon }}">
    <link rel="apple-touch-icon" href="{{ $favicon }}">
@else
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/favicon-64.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
@endif
