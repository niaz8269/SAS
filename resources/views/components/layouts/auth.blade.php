@props(['title' => 'Account'])
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
    <title>{{ $title }} | {{ config('sas.brand.name') }}</title>
    <link rel="preload" href="{{ asset('fonts/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/inter.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-ivory">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:bg-white focus:px-4 focus:py-2">Skip to main content</a>
<div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
    <aside class="relative isolate hidden overflow-hidden bg-navy-900 text-white lg:block" aria-hidden="true">
        <img src="{{ asset('images/hero-shanghai.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover object-center" width="2400" height="1600">
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-navy-900/85 via-navy-800/60 to-navy-900/90"></div>
        <div class="flex h-full flex-col justify-between p-10 xl:p-14">
            <a href="{{ route('home') }}" tabindex="-1"><x-brand light /></a>
            <div>
                <p class="inline-flex items-center gap-2 rounded-full border border-gold/50 bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-gold-soft backdrop-blur"><span class="h-1.5 w-1.5 rounded-full bg-gold"></span>{{ config('sas.brand.tagline') }}</p>
                <p class="mt-5 max-w-md text-3xl font-extrabold leading-tight text-white xl:text-4xl">Your private workspace for scholarships and study abroad.</p>
                <p class="mt-4 max-w-md text-white/80">Save scholarships, plan each application, keep your documents private and see your deadlines in one place.</p>
            </div>
            <p class="text-xs text-white/60">{{ config('sas.legal_entity') }}</p>
        </div>
    </aside>
    <div class="flex flex-col justify-center px-4 py-10 sm:px-8">
        <div class="mx-auto w-full max-w-md">
            <header class="mb-6 flex justify-center lg:hidden"><a href="{{ route('home') }}" aria-label="Back to the {{ config('sas.brand.short') }} homepage"><x-brand /></a></header>
            <a href="{{ route('home') }}" class="mb-4 hidden text-sm font-medium text-navy-700 hover:underline lg:inline-block" aria-label="Back to the {{ config('sas.brand.short') }} homepage">&larr; Back to the website</a>
            <main id="main" class="card p-6 shadow-lg shadow-navy-900/5 sm:p-8">
                @if (session('status'))<div class="mb-4 rounded-md bg-navy-50 px-3 py-2 text-sm text-navy-900" role="status">{{ session('status') }}</div>@endif
                <h1 class="mb-5 text-2xl font-extrabold">{{ $title }}</h1>
                {{ $slot }}
            </main>
            <footer class="mt-6"><p class="text-center text-xs text-muted"><a class="underline" href="{{ route('legal.show', 'privacy') }}">Privacy</a> &middot; <a class="underline" href="{{ route('legal.show', 'terms') }}">Terms</a></p></footer>
        </div>
    </div>
</div>
@livewireScripts
@include('partials.whatsapp')
</body>
</html>