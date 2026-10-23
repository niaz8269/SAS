@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'og_image' => null])
@php
    $fullTitle = ($title ? $title.' | ' : '').config('sas.brand.name');
    $desc = $description ?? \App\Domain\Site\Site::get('default_meta_description', 'Find verified, officially sourced scholarships worldwide and get expert support at every step of your study abroad journey.');
    $nav = \App\Domain\Site\Site::menu('header');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/favicon-64.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#123a7a">
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
    <link rel="preload" href="{{ asset('fonts/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/inter.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:shadow">Skip to main content</a>

<header class="sticky top-0 z-40 border-b border-line bg-white/90 shadow-[0_1px_0_rgba(18,58,122,0.04),0_8px_24px_-16px_rgba(18,58,122,0.25)] backdrop-blur-md" x-data="{ open: false }" @keydown.escape.window="open = false">
    <div class="h-1 bg-gradient-to-r from-navy-800 via-navy-600 to-gold" aria-hidden="true"></div>
    <div class="flex h-[5.25rem] w-full items-center justify-between gap-4 pl-1.5 pr-4 sm:pr-6 lg:pr-8">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ config('sas.brand.name') }} – home"><x-brand class="[&_img]:!h-14 sm:[&_img]:!h-[4.25rem] [&_img]:!max-w-none" /></a>

        <nav aria-label="Primary" class="hidden min-w-0 flex-1 justify-center min-[1330px]:flex">
            <ul class="flex items-center gap-0.5 whitespace-nowrap text-sm font-medium">
                @foreach ($nav as $item)
                    <li><a href="{{ $item['url'] }}" @if($item['new_tab']) target="_blank" rel="noopener" @endif @if($item['active']) aria-current="page" @endif
                           class="group relative block rounded-lg px-2.5 py-2 transition hover:bg-navy-50 hover:text-navy-800 {{ $item['active'] ? 'font-semibold text-navy-800' : 'text-ink' }}">{{ $item['label'] }}
                           <span class="absolute inset-x-2.5 -bottom-0.5 h-0.5 rounded-full bg-gold transition {{ $item['active'] ? 'opacity-100' : 'opacity-0 group-hover:opacity-60' }}" aria-hidden="true"></span></a></li>
                @endforeach
            </ul>
        </nav>

        <div class="hidden shrink-0 items-center gap-2 whitespace-nowrap min-[1330px]:ml-3 min-[1330px]:flex">
            @auth
                <a href="{{ auth()->user()->isStaff() ? url('/admin') : route('student.dashboard') }}" class="rounded-lg px-2.5 py-2 text-sm font-medium text-ink transition hover:bg-navy-50">{{ auth()->user()->isStaff() ? 'Staff admin' : 'Student dashboard' }}</a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-2.5 py-2 text-sm font-medium text-ink transition hover:bg-navy-50">Login</a>
                <a href="{{ route('register') }}" class="rounded-lg border border-navy-200 px-3 py-2 text-sm font-semibold text-navy-800 transition hover:border-navy-500 hover:bg-navy-50">Register</a>
            @endauth
            <a href="{{ route('consultations.create') }}" class="btn-primary rounded-lg px-3.5 py-2.5 shadow-sm shadow-navy-900/20">Book a Consultation <span aria-hidden="true">&rarr;</span></a>
        </div>

        <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-line bg-white transition hover:bg-navy-50 min-[1330px]:hidden" @click="open = !open"
                :aria-expanded="open.toString()" aria-controls="mobile-nav" aria-label="Menu">
            <svg x-show="!open" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <svg x-show="open" x-cloak class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>
    <div id="mobile-nav" x-show="open" x-cloak x-transition.opacity class="border-t border-line bg-white min-[1330px]:hidden" x-trap.noscroll="open">
        <nav aria-label="Mobile" class="container-page py-4">
            <ul class="space-y-1 text-base font-medium">
                @foreach ($nav as $item)
                    <li><a href="{{ $item['url'] }}" @if($item['new_tab']) target="_blank" rel="noopener" @endif @if($item['active']) aria-current="page" @endif class="block rounded-md px-3 py-3 hover:bg-navy-50 {{ $item['active'] ? 'bg-navy-50 text-navy-800' : '' }}">{{ $item['label'] }}</a></li>
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

@if (\App\Domain\Site\Site::get('announcement_enabled') === '1' && \App\Domain\Site\Site::get('announcement_text'))
    <div class="bg-navy-800 px-4 py-2 text-center text-sm text-white" role="region" aria-label="Announcement">
        {{ \App\Domain\Site\Site::get('announcement_text') }}
        @if ($aUrl = \App\Domain\Site\Site::get('announcement_url'))
            <a href="{{ \App\Domain\Site\Site::href($aUrl) }}" class="ml-2 font-semibold underline">Learn more</a>
        @endif
    </div>
@endif

<x-flash />

<main id="main" class="flex-1">
    {{ $slot }}
</main>

@include('partials.footer')
</body>
</html>