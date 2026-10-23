@props(['title' => 'Student workspace'])
@php
    $items = [
        ['Dashboard', route('student.dashboard'), 'student.dashboard'],
        ['Saved scholarships', route('student.saved.index'), 'student.saved.*'],
        ['Application plans', route('student.applications.index'), 'student.applications.*'],
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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ivory-deep/60" x-data="{ nav: false }" @keydown.escape.window="nav = false">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2">Skip to main content</a>
<header class="border-b border-line bg-white">
    <div class="container-page flex h-14 items-center justify-between">
        <div class="flex items-center gap-3">
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-line lg:hidden" @click="nav = !nav" :aria-expanded="nav.toString()" aria-controls="student-nav" aria-label="Workspace menu">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a href="{{ route('student.dashboard') }}"><x-brand /></a>
            <span class="hidden text-sm text-muted sm:inline">Student workspace</span>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('scholarships.index') }}" class="hover:underline">Browse scholarships</a>
            <span class="hidden text-muted md:inline">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-outline !py-1.5">Log out</button></form>
        </div>
    </div>
</header>
<div class="container-page grid gap-6 py-6 lg:grid-cols-[220px_1fr]">
    <nav id="student-nav" aria-label="Student workspace" class="lg:block" :class="nav ? 'block' : 'hidden'">
        <ul class="card space-y-0.5 p-2 text-sm">
            @foreach ($items as [$label, $url, $pattern])
                <li><a href="{{ $url }}" @if(request()->routeIs($pattern)) aria-current="page" @endif
                       class="block rounded-md px-3 py-2 font-medium hover:bg-navy-50 {{ request()->routeIs($pattern) ? 'bg-navy-100 text-navy-900' : 'text-ink' }}">{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>
    <main id="main" class="min-w-0">
        <x-flash />
        {{ $slot }}
    </main>
</div>
</body>
</html>