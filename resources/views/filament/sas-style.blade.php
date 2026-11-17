<style>
    .fi-body :focus-visible { outline: 3px solid #1c4b95; outline-offset: 2px; }
    /* Tables on a phone: keep the page itself from scrolling sideways, let the table scroll inside its box. */
    .fi-ta-content { overflow-x: auto; }
    @media (max-width: 640px) {
        .fi-main { padding-inline: .75rem; }
        .fi-header-heading { font-size: 1.25rem; }
    }
    @media (prefers-reduced-motion: reduce) { .fi-body * { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
</style>
{{-- The SAS look for the staff panel (light mode): pale page, white cards, navy emphasis. Plain CSS in public/css, loaded last so it wins. --}}
<link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}?v={{ @filemtime(public_path('css/admin-shell.css')) }}">
