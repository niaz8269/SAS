@props(['title' => 'Student workspace'])
@php
    $items = [
        ['Dashboard', route('student.dashboard'), 'student.dashboard'],
        ['Saved scholarships', route('student.saved.index'), 'student.saved.*'],
        ['Application plans', route('student.applications.index'), 'student.applications.*'],
        ['Cases and fees', route('student.cases.index'), 'student.cases.*'],
        ['Application form', route('student.form.show'), 'student.form.*'],
        ['Documents', route('student.documents.index'), 'student.documents.*'],
        ['Profile', route('student.profile.edit'), 'student.profile.*'],
        ['My enquiries', route('student.enquiries.index'), 'student.enquiries.*'],
        ['Notifications', route('student.notifications.index'), 'student.notifications.*'],
        ['Security', route('student.security'), 'student.security'],
        ['Privacy and data', route('student.privacy'), 'student.privacy*'],
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
    <meta name="theme-color" content="#123a7a">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | {{ config('sas.brand.short') }} student workspace</title>
    <link rel="preload" href="{{ asset('fonts/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/inter.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-ivory" x-data="{ nav: false }" @keydown.escape.window="nav = false">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2">Skip to main content</a>
<header class="sticky top-0 z-30 border-b border-line bg-white/90 shadow-[0_1px_0_rgba(18,58,122,0.04),0_8px_24px_-16px_rgba(18,58,122,0.25)] backdrop-blur-md">
    <div class="h-1 bg-gradient-to-r from-navy-800 via-navy-600 to-gold" aria-hidden="true"></div>
    <div class="flex h-16 w-full items-center justify-between gap-3 pl-1.5 pr-4 sm:pr-6 lg:pr-8">
        <div class="flex min-w-0 items-center gap-3">
            <button type="button" class="ml-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-line bg-white transition hover:bg-navy-50 lg:hidden" @click="nav = !nav" :aria-expanded="nav.toString()" aria-controls="student-nav" aria-label="Workspace menu">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a href="{{ route('student.dashboard') }}" class="shrink-0" aria-label="{{ config('sas.brand.short') }} student workspace"><x-brand class="[&_img]:!h-10 sm:[&_img]:!h-11 [&_img]:!max-w-none" /></a>
            <span class="hidden border-l border-line pl-3 text-sm font-semibold text-navy-800 sm:inline">Student workspace</span>
        </div>
        <div class="flex shrink-0 items-center gap-1 text-sm font-medium sm:gap-3">
            <a href="{{ route('scholarships.index') }}" class="rounded-lg px-2 py-2 transition hover:bg-navy-50 sm:px-2.5"><span class="sm:hidden">Browse</span><span class="hidden sm:inline">Browse scholarships</span></a>
            <span class="hidden max-w-[12rem] truncate text-muted md:inline">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-outline rounded-lg !py-1.5">Log out</button></form>
        </div>
    </div>
</header>
<div class="container-page grid gap-6 py-6 lg:grid-cols-[220px_1fr]">
    <nav id="student-nav" aria-label="Student workspace" class="lg:sticky lg:top-24 lg:block lg:self-start" :class="nav ? 'block' : 'hidden'">
        <ul class="card space-y-0.5 p-2 text-sm">
            @foreach ($items as [$label, $url, $pattern])
                <li><a href="{{ $url }}" @if(request()->routeIs($pattern)) aria-current="page" @endif
                       class="block rounded-md border-l-4 px-3 py-2 font-medium transition hover:bg-navy-50 {{ request()->routeIs($pattern) ? 'border-gold bg-navy-50 text-navy-900' : 'border-transparent text-ink' }}">{{ $label }}</a></li>
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