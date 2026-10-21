@props(['title' => 'Partner area'])
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | {{ config('sas.brand.short') }} partner area</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ivory">
<header class="border-b border-line bg-forest-900 text-white">
    <div class="container-page flex h-14 items-center justify-between">
        <x-brand compact light />
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-lime !py-1.5">Log out</button></form>
    </div>
</header>
<main id="main" class="container-page py-8">
    <x-flash />
    {{ $slot }}
</main>
</body>
</html>