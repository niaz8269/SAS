@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'og_image' => null])
@php
    $fullTitle = ($title ? $title.' | ' : '').config('sas.brand.name');
    $desc = $description ?? 'Find verified, officially sourced scholarships worldwide and get expert support at every step of your study abroad journey.';
    $nav = [
        ['Home', route('home'), request()->routeIs('home')],
        ['Scholarships', route('scholarships.index'), request()->routeIs('scholarships.*')],
        ['Study Destinations', route('destinations.index'), request()->routeIs('destinations.*')],
        ['Our Services', route('services.index'), request()->routeIs('services.*')],
        ['Resources', route('resources.index'), request()->routeIs('resources.*')],
        ['About', route('about'), request()->routeIs('about')],
        ['Contact', route('contact'), request()->routeIs('contact')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $desc }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    @if ($noindex)<meta name="robots" content="noindex, nofollow">@endif
    <meta property="og:site_name" content="{{ config('sas.brand.name') }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $desc }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    @if ($og_image)<meta property="og:image" content="{{ $og_image }}">@endif
    <meta name="twitter:card" content="summary">
    {{ $head ?? '' }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:shadow">Skip to main content</a>

<header class="sticky top-0 z-40 border-b border-line bg-white/95 backdrop-blur" x-data="{ open: false }" @keydown.escape.window="open = false">
    <div class="container-page flex h-16 items-center justify-between gap-4">
        <a href="{{ route('home') }}" aria-label="{{ config('sas.brand.name') }} – home"><x-brand /></a>

        <nav aria-label="Primary" class="hidden lg:block">
            <ul class="flex items-center gap-1 text-sm font-medium">
                @foreach ($nav as [$label, $url, $active])
                    <li><a href="{{ $url }}" @if($active) aria-current="page" @endif
                           class="rounded-md px-3 py-2 hover:bg-forest-50 {{ $active ? 'text-forest-800 font-semibold border-b-2 border-forest-700 rounded-b-none' : 'text-ink' }}">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>

        <div class="hidden items-center gap-3 lg:flex">
            @auth
                <a href="{{ auth()->user()->isStaff() ? url('/admin') : route('student.dashboard') }}" class="text-sm font-medium hover:underline">{{ auth()->user()->isStaff() ? 'Staff admin' : 'Student dashboard' }}</a>
            @else
                <a href="{{ route('login') }}" class="text-sm font-medium hover:underline">Login</a>
                <span class="text-line" aria-hidden="true">/</span>
                <a href="{{ route('register') }}" class="text-sm font-medium hover:underline">Register</a>
            @endauth
            <a href="{{ route('consultations.create') }}" class="btn-primary">Book a Consultation <span aria-hidden="true">&rarr;</span></a>
        </div>

        <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-line lg:hidden" @click="open = !open"
                :aria-expanded="open.toString()" aria-controls="mobile-nav" aria-label="Menu">
            <svg x-show="!open" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <svg x-show="open" x-cloak class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    <div id="mobile-nav" x-show="open" x-cloak x-transition.opacity class="border-t border-line bg-white lg:hidden" x-trap.noscroll="open">
        <nav aria-label="Mobile" class="container-page py-4">
            <ul class="space-y-1 text-base font-medium">
                @foreach ($nav as [$label, $url, $active])
                    <li><a href="{{ $url }}" @if($active) aria-current="page" @endif class="block rounded-md px-3 py-3 hover:bg-forest-50 {{ $active ? 'bg-forest-50 text-forest-800' : '' }}">{{ $label }}</a></li>
                @endforeach
            </ul>
            <div class="mt-4 grid gap-2">
                @auth
                    <a href="{{ auth()->user()->isStaff() ? url('/admin') : route('student.dashboard') }}" class="btn-outline">{{ auth()->user()->isStaff() ? 'Staff admin' : 'Student dashboard' }}</a>
                @else
                    <a href="{{ route('login') }}" class="btn-outline">Login</a>
                    <a href="{{ route('register') }}" class="btn-outline">Register</a>
                @endauth
                <a href="{{ route('consultations.create') }}" class="btn-primary">Book a Consultation</a>
            </div>
        </nav>
    </div>
</header>

<x-flash />

<main id="main" class="flex-1">
    {{ $slot }}
</main>

@include('partials.footer')
</body>
</html>