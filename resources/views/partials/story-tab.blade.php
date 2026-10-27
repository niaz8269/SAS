{{-- Two vertical tabs fixed to the left edge, halfway down every public page: "Share Your Story" for students and "Be a Partner" for agencies.
     Both are exactly the same size (width and height), and sit close together so they read as one pair. --}}
@unless (request()->routeIs('stories.create', 'stories.thanks'))
    @php
        $tab = 'flex h-[9.5rem] w-5 items-center justify-center whitespace-nowrap rounded-r-xl text-[0.7rem] font-bold uppercase tracking-wider shadow-lg shadow-black/25 transition [writing-mode:vertical-rl] sm:h-[12rem] sm:w-7 sm:text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-900';
    @endphp
    <div class="fixed left-0 top-1/2 z-40 flex -translate-y-1/2 flex-col gap-2 print:hidden">
        <a href="{{ route('stories.create') }}" aria-label="Share your story with {{ config('sas.brand.short') }}" class="{{ $tab }} bg-gold text-navy-900 hover:bg-gold-soft">
            <span class="inline-block rotate-180">Share Your Story</span>
        </a>
        <a href="{{ route('contact', ['category' => 'partnership']) }}" aria-label="Be a partner of {{ config('sas.brand.short') }}: recruiting agents and organisations" class="{{ $tab }} bg-navy-800 text-white hover:bg-navy-700">
            <span class="inline-block rotate-180">Be a Partner</span>
        </a>
    </div>
@endunless
