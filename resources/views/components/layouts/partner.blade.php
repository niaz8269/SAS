@props(['title' => 'Partner area'])
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/favicon-64.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#123a7a">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | {{ config('sas.brand.short') }} partner area</title>
    <link rel="preload" href="{{ asset('fonts/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/inter.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-ivory">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2">Skip to main content</a>
@php
    $nav = [['Overview', route('partner.home'), 'partner.home'], ['Submissions', route('partner.submissions.index'), 'partner.submissions.*'], ['Organisation', route('partner.organisation.edit'), 'partner.organisation.*']];
@endphp
<header class="border-b border-navy-800 bg-navy-900 text-white">
    <div class="h-1 bg-gradient-to-r from-navy-600 via-gold to-gold" aria-hidden="true"></div>
    <div class="flex h-16 w-full items-center justify-between gap-3 pl-1.5 pr-4 sm:pr-6 lg:pr-8">
        <div class="flex min-w-0 items-center gap-3"><a href="{{ route('partner.home') }}" aria-label="{{ config('sas.brand.short') }} partner area"><x-brand light class="[&>span]:!p-1 [&_img]:!h-11 [&_img]:!max-w-none" /></a><span class="hidden border-l border-white/20 pl-3 text-sm font-semibold text-gold-soft sm:inline">Partner area</span></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-gold rounded-lg !py-1.5">Log out</button></form>
    </div>
    <nav aria-label="Partner area" class="border-t border-white/10 bg-navy-800/60">
        <ul class="container-page flex gap-1 overflow-x-auto py-1 text-sm font-medium">
            @foreach ($nav as [$label, $url, $pattern])
                <li><a href="{{ $url }}" @if(request()->routeIs($pattern)) aria-current="page" @endif class="block whitespace-nowrap rounded-md px-3 py-2 transition hover:bg-white/10 {{ request()->routeIs($pattern) ? 'bg-white/15 text-white' : 'text-white/80' }}">{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>
</header>
<main id="main" class="container-page py-8">
    <x-flash />
    {{ $slot }}
</main>
@livewireScripts
</body>
</html>