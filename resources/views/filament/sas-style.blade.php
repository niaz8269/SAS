<style>
    /* The SAS look for the staff panel (light mode): ivory page, navy headings, gold accent, the same focus ring as the student and agent portals. */
    html:not(.dark) .fi-body { background-color: #f7f3eb; color: #14203a; }
    html:not(.dark) .fi-header-heading,
    html:not(.dark) .fi-section-header-heading,
    html:not(.dark) .fi-ta-header-heading { color: #0a2250; }
    html:not(.dark) .fi-topbar,
    html:not(.dark) .fi-sidebar { background-color: #ffffff; border-color: #e2dccc; }
    html:not(.dark) .fi-sidebar-item.fi-active > a { background-color: #eef3fb; }
    html:not(.dark) .fi-sidebar-group-label { color: #4a5873; letter-spacing: .04em; text-transform: uppercase; font-size: .7rem; }
    html:not(.dark) .fi-section,
    html:not(.dark) .fi-ta-ctn,
    html:not(.dark) .fi-wi-stats-overview-stat { border-color: #e2dccc; }
    .fi-body :focus-visible { outline: 3px solid #1c4b95; outline-offset: 2px; }
    /* Tables on a phone: keep the page itself from scrolling sideways, let the table scroll inside its box. */
    .fi-ta-content { overflow-x: auto; }
    @media (max-width: 640px) {
        .fi-main { padding-inline: .75rem; }
        .fi-header-heading { font-size: 1.25rem; }
    }
    @media (prefers-reduced-motion: reduce) { .fi-body * { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
</style>
