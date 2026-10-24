@props(['title' => 'My application'])
@php
    $user = auth()->user();
    $context = app()->bound(\App\Support\StudentContext::class) ? app(\App\Support\StudentContext::class) : null;
    $agent = $context?->isAgent();
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.favicon')
    <meta name="theme-color" content="#0b4a8f">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | {{ config('sas.brand.short') }} application</title>
    <link rel="preload" href="{{ asset('fonts/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/inter.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-white text-[#333]">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2">Skip to main content</a>

<header class="bg-[#0b4a8f] text-white">
    <div class="flex h-[4.5rem] items-center justify-between gap-3 px-3 sm:px-6">
        <a href="{{ $agent ? route('partner.home') : route('student.dashboard') }}" aria-label="{{ config('sas.brand.short') }} home"><span class="flex items-center gap-3"><x-brand compact class="[&_img]:!h-[3.375rem] [&_img]:!w-[3.375rem] [&_img]:rounded-full [&_img]:ring-2 [&_img]:ring-white" /><span class="hidden text-[1.625rem] font-semibold tracking-[0.25em] sm:inline" aria-hidden="true">SAS</span></span></a>
        <nav aria-label="Application" class="hidden items-center gap-5 text-[0.9375rem] md:flex">
            @if ($agent)
                <a class="hover:underline" href="{{ route('partner.home') }}">Agent backend</a>
                <a class="hover:underline" href="{{ route('partner.student.show', $context->student->public_id) }}">{{ $context->student->name }}</a>
                <a class="hover:underline" href="{{ $context->route('applications.index') }}">Applications</a>
            @else
                <a class="hover:underline" href="{{ route('student.dashboard') }}">Home</a>
                <a class="hover:underline" href="{{ route('student.form.show') }}">Your details</a>
                <a class="hover:underline" href="{{ route('student.choose.index') }}">Choose scholarships</a>
                <a class="hover:underline" href="{{ route('student.applications.index') }}">My applications</a>
            @endif
        </nav>
        <details class="relative text-sm">
            <summary class="flex cursor-pointer list-none items-center gap-2 py-2">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-white text-sm font-bold text-[#0b4a8f]" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(($agent ? $context->student->name : $user->name), 0, 2)) }}</span>
                <span class="hidden max-w-[10rem] truncate sm:inline">{{ $agent ? $context->student->name : $user->name }}</span><span aria-hidden="true">▾</span>
            </summary>
            <ul class="absolute right-0 z-40 mt-1 w-56 border border-[#ddd] bg-white py-1 text-[#333] shadow-lg">
                @if ($agent)
                    <li><a class="block px-4 py-2 hover:bg-[#f0f6fa]" href="{{ route('partner.home') }}">Back to the agent backend</a></li>
                @else
                    <li><a class="block px-4 py-2 hover:bg-[#f0f6fa]" href="{{ route('student.dashboard') }}">Home</a></li>
                    <li><a class="block px-4 py-2 hover:bg-[#f0f6fa]" href="{{ route('student.documents.index') }}">My documents</a></li>
                    <li><a class="block px-4 py-2 hover:bg-[#f0f6fa]" href="{{ route('student.profile.edit') }}">Profile</a></li>
                @endif
                <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="block w-full px-4 py-2 text-left hover:bg-[#f0f6fa]">Log out</button></form></li>
            </ul>
        </details>
    </div>
</header>

<main id="main" class="mx-auto w-full max-w-[68.75rem] px-4 py-8 sm:px-6">
    <x-flash />
    {{ $slot }}
</main>

<footer class="border-t border-[#e5e5e5] py-5 text-center text-sm text-[#666]">
    <strong class="text-[#0b4a8f]">{{ config('sas.brand.short') }}</strong> Scholarships Application Services &copy; {{ date('Y') }} Customer Service: <a class="hover:underline" href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a>
</footer>
@livewireScripts
@include('partials.whatsapp')
</body>
</html>
