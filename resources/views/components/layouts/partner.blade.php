@props(['title' => 'Partner area'])
@php
    $user = auth()->user();
    $organisation = $user->partnerOrganisation;
    $nav = [['Backend home', route('partner.home'), 'partner.home'], ['Student list', route('partner.students.index'), ['partner.students.*', 'partner.student.*']], ['Application list', route('partner.applications.index'), 'partner.applications.*']];
    $nav[] = ['Organisation data', route('partner.organisation.edit'), 'partner.organisation.*'];
@endphp
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
<header class="border-b border-navy-800 bg-navy-900 text-white">
    <div class="h-1 bg-gradient-to-r from-navy-600 via-gold to-gold" aria-hidden="true"></div>
    <div class="flex h-16 w-full items-center justify-between gap-3 pl-1.5 pr-4 sm:pr-6 lg:pr-8">
        <div class="flex min-w-0 items-center gap-3"><a href="{{ route('partner.home') }}" aria-label="{{ config('sas.brand.short') }} partner area"><x-brand light class="[&>span]:!p-1 [&_img]:!h-11 [&_img]:!max-w-none" /></a>
            <span class="hidden truncate border-l border-white/20 pl-3 text-sm font-semibold text-gold-soft sm:inline">Recruiting agent &middot; {{ $organisation?->name }}</span></div>
        <details class="relative text-sm">
            <summary class="cursor-pointer list-none rounded-lg px-3 py-2 hover:bg-white/10">{{ $user->name }} <span aria-hidden="true">▾</span></summary>
            <ul class="absolute right-0 z-40 mt-1 w-52 rounded-lg border border-line bg-white p-1 text-ink shadow-lg">
                <li><a class="block rounded-md px-3 py-2 hover:bg-navy-50" href="{{ route('partner.organisation.edit') }}">Edit organisation data</a></li>
                <li><a class="block rounded-md px-3 py-2 hover:bg-navy-50" href="{{ route('partner.password.edit') }}">Change password</a></li>
                <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="block w-full rounded-md px-3 py-2 text-left hover:bg-navy-50">Log out</button></form></li>
            </ul>
        </details>
    </div>
</header>
<div class="container-page grid gap-6 py-6 lg:grid-cols-[210px_1fr]">
    <nav aria-label="Partner area" class="lg:sticky lg:top-6 lg:self-start">
        <ul class="card flex gap-1 overflow-x-auto p-2 text-sm lg:block lg:space-y-0.5 lg:overflow-visible">
            @foreach ($nav as [$label, $url, $patterns])
                @php($on = request()->routeIs(...(array) $patterns))
                <li><a href="{{ $url }}" @if($on) aria-current="page" @endif class="block whitespace-nowrap rounded-md border-l-4 px-3 py-2 font-medium transition hover:bg-navy-50 {{ $on ? 'border-gold bg-navy-50 text-navy-900' : 'border-transparent text-ink' }}">{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>
    <main id="main" class="min-w-0">
        <x-flash />
        {{ $slot }}
    </main>
</div>
@livewireScripts
</body>
</html>
