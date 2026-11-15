@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'og_image' => null, 'crumbs' => null])
@php
    // Numbered pages of a list (?page=2) are real pages: each names itself as canonical and gets its own title and description, so none is read as a copy of page 1.
    // Any other query (a search or filter) names the plain address as canonical.
    $pageNo = (int) request()->query('page');
    $numbered = $pageNo > 1 && array_keys(request()->query()) === ['page'];
    $fullTitle = $title ? $title.($numbered ? ' (page '.$pageNo.')' : '').' | '.config('sas.brand.short') : config('sas.brand.name'); // the short brand keeps page titles near the 60 characters search results show
    $desc = $description ?? \App\Domain\Site\Site::get('default_meta_description', 'Find verified, officially sourced scholarships worldwide and get expert support at every step of your study abroad journey.');
    if ($numbered) {
        $desc = rtrim($desc, ' .').'. Page '.$pageNo.'.';
    }
    $nav = \App\Domain\Site\Site::menu('header');
    // The menu is edited in the Admin. Home is drawn as an icon, and Resources, Blogs, About and Contact share one "More" dropdown (grouped by their address, so a label changed in the Admin still lands in the right place).
    $pathOf = fn ($i) => '/'.trim((string) parse_url($i['url'], PHP_URL_PATH), '/');
    $moreItems = array_values(array_filter($nav, fn ($i) => in_array($pathOf($i), ['/resources', '/blog', '/about', '/contact'], true)));
    $moreOrder = ['/about' => 0, '/resources' => 1, '/blog' => 2, '/contact' => 3]; // About first, then the rest in their usual order
    usort($moreItems, fn ($x, $y) => ($moreOrder[$pathOf($x)] ?? 9) <=> ($moreOrder[$pathOf($y)] ?? 9));
    $navView = [];
    $moreAdded = false;
    foreach ($nav as $i) {
        if (in_array($pathOf($i), ['/resources', '/blog', '/about', '/contact'], true)) {
            if (! $moreAdded) {
                $navView[] = ['more' => $moreItems];
                $moreAdded = true;
            }

            continue;
        }
        $navView[] = ['item' => $i, 'home' => $pathOf($i) === '/'];
    }
    $moreActive = collect($moreItems)->contains(fn ($i) => $i['active']);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.favicon')
    <meta name="theme-color" content="#123a7a">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $desc }}">
    @php
        // the address on the main domain, whichever host name the visit used (www, http, a staging name)
        $canonicalUrl = $canonical ?? rtrim((string) config('app.url'), '/').request()->getPathInfo().($numbered ? '?page='.$pageNo : '');
        $verify = array_filter(['google-site-verification' => \App\Domain\Site\Site::get('google_site_verification'), 'msvalidate.01' => \App\Domain\Site\Site::get('bing_site_verification')]);
        $shareImage = $og_image ?? \App\Domain\Site\Site::mediaUrl('og_image_path') ?? asset('images/og-default.jpg');
        $shareIsBundled = ! $og_image && ! \App\Domain\Site\Site::get('og_image_path');
    @endphp
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @if ($noindex)<meta name="robots" content="noindex, nofollow">@else<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">@endif
    @foreach ($verify as $name => $token)<meta name="{{ $name }}" content="{{ $token }}">
    @endforeach
    @if ($crumbs)
        <x-jsonld :data="['@type' => 'BreadcrumbList', 'itemListElement' => collect($crumbs)->values()->map(fn ($c, $i) => array_filter(['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] ?? null]))->all()]" />
    @endif
    <meta property="og:site_name" content="{{ config('sas.brand.name') }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $desc }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="en_US">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $shareImage }}">
    @if ($shareIsBundled)<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">@endif
    <meta property="og:image:alt" content="{{ config('sas.brand.name') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $fullTitle }}">
    <meta name="twitter:description" content="{{ $desc }}">
    <meta name="twitter:image" content="{{ $shareImage }}">
    {{ $head ?? '' }}
    <link rel="preload" href="{{ asset('fonts/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/inter.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen flex flex-col">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:shadow">Skip to main content</a>

<header class="sticky top-0 z-40 border-b border-line bg-white/90 shadow-[0_0.0625rem_0_rgba(18,58,122,0.04),0_0.5rem_1.5rem_-1rem_rgba(18,58,122,0.25)] backdrop-blur-md" x-data="{ open: false }" @keydown.escape.window="open = false">
    <div class="h-1 bg-gradient-to-r from-navy-800 via-navy-600 to-gold" aria-hidden="true"></div>
    <div class="flex h-[5.5rem] w-full items-center justify-between gap-4 pl-1.5 pr-4 sm:pr-6 lg:pr-8">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ config('sas.brand.name') }} – home"><x-brand class="[&_img]:!h-[3.75rem] min-[1200px]:[&_img]:!h-[4.75rem] [&_img]:!max-w-none" /></a>

        <nav aria-label="Primary" class="hidden min-w-0 flex-1 justify-center min-[1100px]:flex">
            <ul class="flex items-center gap-0.5 whitespace-nowrap text-[0.8125rem] font-medium min-[1200px]:text-base">
                @foreach ($navView as $entry)
                    @if (isset($entry['more']))
                        <li class="relative" x-data="{ more: false }" @mouseenter="more = true" @mouseleave="more = false" @click.outside="more = false" @keydown.escape="more = false">
                            <button type="button" @click="window.matchMedia('(hover: hover)').matches ? (more = true) : (more = ! more)" :aria-expanded="more.toString()" aria-haspopup="true" aria-controls="nav-more" aria-label="More pages" title="More"
                                    class="group relative inline-flex items-center gap-0.5 rounded-lg px-1.5 py-2 transition hover:bg-navy-50 hover:text-navy-800 {{ $moreActive ? 'text-navy-800' : 'text-navy-700' }}">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                                <svg class="h-4 w-4 transition" :class="more && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                                <span class="absolute inset-x-1.5 -bottom-0.5 h-0.5 rounded-full bg-gold transition {{ $moreActive ? 'opacity-100' : 'opacity-0 group-hover:opacity-60' }}" aria-hidden="true"></span></button>
                            <div class="absolute right-0 z-50" style="top:100%;padding-top:0.25rem" x-show="more" x-cloak x-transition.opacity>
                            <ul id="nav-more" class="min-w-[12rem] rounded-xl border border-line bg-white p-2 text-base shadow-lg">
                                @foreach ($entry['more'] as $m)
                                    <li><a href="{{ $m['url'] }}" @if($m['new_tab']) target="_blank" rel="noopener" @endif @if($m['active']) aria-current="page" @endif class="block rounded-md px-3 py-2 hover:bg-navy-50 hover:text-navy-800 {{ $m['active'] ? 'bg-navy-50 font-semibold text-navy-800' : 'text-ink' }}">{{ $m['label'] }}</a></li>
                                @endforeach
                            </ul>
                            </div>
                        </li>
                    @else
                        @php($item = $entry['item'])
                        <li><a href="{{ $item['url'] }}" @if($item['new_tab']) target="_blank" rel="noopener" @endif @if($item['active']) aria-current="page" @endif @if($entry['home']) aria-label="{{ $item['label'] }}" title="{{ $item['label'] }}" @endif
                               class="group relative block rounded-lg px-1.5 py-2 transition hover:bg-navy-50 min-[1200px]:px-1.5 hover:text-navy-800 {{ $item['active'] ? 'font-semibold text-navy-800' : 'text-ink' }}">
                               @if ($entry['home'])<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/></svg>@else{{ $item['label'] }}@endif
                               <span class="absolute inset-x-1.5 -bottom-0.5 min-[1200px]:inset-x-1.5 h-0.5 rounded-full bg-gold transition {{ $item['active'] ? 'opacity-100' : 'opacity-0 group-hover:opacity-60' }}" aria-hidden="true"></span></a></li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <div class="hidden shrink-0 items-center gap-2 whitespace-nowrap min-[1100px]:ml-3 min-[1100px]:flex">
            @auth
                <a href="{{ auth()->user()->isStaff() ? url('/admin') : route('student.dashboard') }}" class="rounded-lg px-2 py-2 text-[0.8125rem] font-medium text-ink transition hover:bg-navy-50 min-[1200px]:px-2 min-[1200px]:text-base">{{ auth()->user()->isStaff() ? 'Staff admin' : 'Student dashboard' }}</a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-2 py-2 text-[0.8125rem] font-medium text-ink transition hover:bg-navy-50 min-[1200px]:px-2 min-[1200px]:text-base">Login</a>
            @endauth
            <a href="{{ route('consultations.create') }}" class="btn-primary rounded-lg px-3 py-2.5 text-[0.8125rem] shadow-sm min-[1200px]:px-3.5 min-[1200px]:text-base shadow-navy-900/20">Book a Consultation <span aria-hidden="true">&rarr;</span></a>
        </div>

        <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-line bg-white transition hover:bg-navy-50 min-[1100px]:hidden" @click="open = !open"
                :aria-expanded="open.toString()" aria-controls="mobile-nav" aria-label="Menu">
            <svg x-show="!open" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <svg x-show="open" x-cloak class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>
    <div id="mobile-nav" x-show="open" x-cloak x-transition.opacity class="border-t border-line bg-white min-[1100px]:hidden" x-trap.noscroll="open">
        <nav aria-label="Mobile" class="container-page py-4">
            <ul class="space-y-1 text-base font-medium">
                @foreach ($navView as $entry)
                    @if (isset($entry['more']))
                        <li x-data="{ more: {{ $moreActive ? 'true' : 'false' }} }">
                            <button type="button" @click="more = ! more" :aria-expanded="more.toString()" aria-controls="mobile-more" class="flex w-full items-center justify-between rounded-md px-3 py-3 text-left hover:bg-navy-50 {{ $moreActive ? 'text-navy-800' : '' }}">More
                                <svg class="h-5 w-5 transition" :class="more && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg></button>
                            <ul id="mobile-more" x-show="more" x-cloak class="mt-1 space-y-1 border-l border-line pl-3">
                                @foreach ($entry['more'] as $m)
                                    <li><a href="{{ $m['url'] }}" @if($m['new_tab']) target="_blank" rel="noopener" @endif @if($m['active']) aria-current="page" @endif class="block rounded-md px-3 py-3 hover:bg-navy-50 {{ $m['active'] ? 'bg-navy-50 text-navy-800' : '' }}">{{ $m['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        @php($item = $entry['item'])
                        <li><a href="{{ $item['url'] }}" @if($item['new_tab']) target="_blank" rel="noopener" @endif @if($item['active']) aria-current="page" @endif class="flex items-center gap-2 rounded-md px-3 py-3 hover:bg-navy-50 {{ $item['active'] ? 'bg-navy-50 text-navy-800' : '' }}">@if ($entry['home'])<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/></svg>@endif{{ $item['label'] }}</a></li>
                    @endif
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
@livewireScripts
@include('partials.whatsapp')
@include('partials.story-tab')
@include('partials.tawk')
</body>
</html>
