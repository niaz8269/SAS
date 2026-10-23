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
</head>
<body class="min-h-screen bg-ivory">
<header class="border-b border-line bg-navy-900 text-white">
    <div class="container-page flex h-14 items-center justify-between">
        <x-brand light />
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-gold !py-1.5">Log out</button></form>
    </div>
</header>
<main id="main" class="container-page py-8">
    <x-flash />
    {{ $slot }}
</main>
</body>
</html>