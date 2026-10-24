@props(['title' => 'Backend home', 'crumb' => null])
@php
    $user = auth()->user();
    $organisation = $user->partnerOrganisation;
    $icons = [
        'home' => 'M3 12 12 3l9 9M5 10v10h5v-6h4v6h5V10',
        'list' => 'M4 6h16M4 12h16M4 18h16',
        'apps' => 'M7 3h7l5 5v13H7V3Zm7 0v5h5M10 13h6M10 17h6',
        'money' => 'M3 7h18v10H3V7Zm9 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM6 10v4M18 10v4',
    ];
    $nav = [
        ['Backend home', route('partner.home'), 'partner.home', 'home'],
        ['Student list', route('partner.students.index'), ['partner.students.*', 'partner.student.*'], 'list'],
        ['Application list', route('partner.applications.index'), 'partner.applications.*', 'apps'],
        ['Fees statement', route('partner.statement'), 'partner.statement', 'money'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/favicon-64.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#428bb6">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | {{ config('sas.brand.short') }} agent portal</title>
    <link rel="preload" href="{{ asset('fonts/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/inter.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-white text-[13px] text-[#333]" x-data="{ collapsed: false, menu: false }">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2">Skip to main content</a>

{{-- Header: logo, the portal's name, and the signed-in user --}}
<header class="bg-[#428bb6] text-white">
    <div class="flex h-[70px] items-center justify-between gap-3 pl-3">
        <div class="flex min-w-0 items-center gap-3">
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded border border-white/40 lg:hidden" @click="menu = !menu" :aria-expanded="menu.toString()" aria-controls="agent-nav" aria-label="Menu">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a href="{{ route('partner.home') }}" aria-label="{{ config('sas.brand.short') }} agent portal"><x-brand compact class="[&_img]:!h-[54px] [&_img]:!w-[54px] [&_img]:rounded-full [&_img]:ring-2 [&_img]:ring-white" /></a>
            <a href="{{ route('partner.home') }}" class="hidden text-[20px] font-semibold tracking-[0.25em] sm:inline" tabindex="-1" aria-hidden="true">SAS</a>
            <span class="hidden border-l border-white/50 pl-3 text-xl font-light sm:inline">Recruiting agents</span>
        </div>
        <details class="relative h-full text-sm">
            <summary class="flex h-full cursor-pointer list-none items-center gap-3 bg-[#5a9bc4] px-4 hover:bg-[#6aa6cc]">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white text-sm font-bold text-[#428bb6]" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 2)) }}</span>
                <span class="hidden text-left leading-tight sm:block"><span class="block text-xs">welcome,</span><span class="block max-w-[11rem] truncate text-base font-semibold">{{ $user->name }}</span></span>
                <span aria-hidden="true">▾</span>
            </summary>
            <ul class="absolute right-0 z-40 w-56 border border-[#ddd] bg-white py-1 text-[#333] shadow-lg">
                <li><a class="block px-4 py-2 hover:bg-[#f0f6fa]" href="{{ route('partner.organisation.edit') }}">Edit data</a></li>
                <li><a class="block px-4 py-2 hover:bg-[#f0f6fa]" href="{{ route('partner.password.edit') }}">Change password</a></li>
                <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="block w-full px-4 py-2 text-left hover:bg-[#f0f6fa]">Log out</button></form></li>
            </ul>
        </details>
    </div>
</header>

<div class="flex min-h-[calc(100vh-70px-64px)]">
    {{-- Sidebar --}}
    <nav id="agent-nav" aria-label="Agent portal" class="shrink-0 border-r border-[#e5e5e5] bg-[#f5f5f5] transition-all" :class="[collapsed ? 'w-16' : 'w-52 lg:w-[230px]', menu ? 'block' : 'hidden lg:block']">
        <ul>
            @foreach ($nav as [$label, $url, $patterns, $icon])
                @php($on = request()->routeIs(...(array) $patterns))
                <li><a href="{{ $url }}" @if($on) aria-current="page" @endif title="{{ $label }}"
                       class="flex h-[46px] items-center gap-4 border-b border-[#e5e5e5] border-l-4 px-5 text-[14px] transition hover:bg-white {{ $on ? 'border-l-[#428bb6] bg-white text-[#428bb6]' : 'border-l-transparent text-[#333]' }}">
                    <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons[$icon] }}"/></svg>
                    <span x-show="!collapsed" class="truncate">{{ $label }}</span></a></li>
            @endforeach
        </ul>
        <div class="relative my-4 hidden text-center lg:block"><span class="absolute inset-x-5 top-1/2 border-t border-[#ddd]" aria-hidden="true"></span>
            <button type="button" @click="collapsed = !collapsed" class="relative inline-flex h-8 w-8 items-center justify-center rounded-full border border-[#ccc] bg-white text-[#666]" :aria-label="collapsed ? 'Expand the menu' : 'Collapse the menu'"><span x-text="collapsed ? '»' : '«'" aria-hidden="true">«</span></button></div>
    </nav>

    {{-- Page --}}
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 border-b border-[#e5e5e5] bg-[#f5f5f5] px-5 py-3 text-[13px]">
            <svg class="h-5 w-5 text-[#555]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons['home'] }}"/></svg>
            <a class="text-[#428bb6] hover:underline" href="{{ route('partner.home') }}">Backend</a>
            <span class="text-[#999]" aria-hidden="true">›</span>
            <span>{{ $crumb ?? $title }}</span>
        </div>
        <main id="main" class="px-5 py-3 sm:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>

<footer class="border-t border-[#e5e5e5] py-3 text-center text-[13px]">
    <strong class="text-[#428bb6]">{{ config('sas.brand.short') }}</strong> Scholarships Application Services &copy; {{ date('Y') }} Customer Service: <a class="hover:underline" href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a>
</footer>
@livewireScripts
</body>
</html>
