{{-- A vertical tab fixed to the left edge, halfway down every public page: it invites students to send their story. --}}
@unless (request()->routeIs('stories.create', 'stories.thanks'))
    <a href="{{ route('stories.create') }}" aria-label="Share your story with {{ config('sas.brand.short') }}"
       class="fixed left-0 top-1/2 z-40 -translate-y-1/2 whitespace-nowrap rounded-r-xl bg-gold px-1 py-3 text-[0.7rem] font-bold uppercase tracking-wider sm:px-2.5 sm:py-4 sm:text-sm text-navy-900 shadow-lg shadow-black/25 transition [writing-mode:vertical-rl] hover:bg-gold-soft sm:hover:pl-3.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-900 print:hidden">
        <span class="inline-block rotate-180">Share Your Story</span>
    </a>
@endunless
