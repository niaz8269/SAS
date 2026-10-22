@props(['title' => 'Account'])
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | {{ config('sas.brand.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ivory">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:bg-white focus:px-4 focus:py-2">Skip to main content</a>
<div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
    <header class="mb-6 self-center"><a href="{{ route('home') }}" aria-label="Back to the {{ config('sas.brand.short') }} homepage"><x-brand /></a></header>
    <main id="main" class="card p-6 sm:p-8">
        @if (session('status'))<div class="mb-4 rounded-md bg-forest-50 px-3 py-2 text-sm text-forest-900" role="status">{{ session('status') }}</div>@endif
        <h1 class="mb-5 text-2xl font-extrabold">{{ $title }}</h1>
        {{ $slot }}
    </main>
    <footer class="mt-6"><p class="text-center text-xs text-muted"><a class="underline" href="{{ route('legal.show', 'privacy') }}">Privacy</a> · <a class="underline" href="{{ route('legal.show', 'terms') }}">Terms</a></p></footer>
</div>
</body>
</html>