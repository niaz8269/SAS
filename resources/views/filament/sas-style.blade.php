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

    /* A calmer, more professional surface: soft cards, quiet table headers, clear hover and selection states, even spacing. */
    html:not(.dark) .fi-topbar { box-shadow: 0 1px 0 #e2dccc; }
    html:not(.dark) .fi-section,
    html:not(.dark) .fi-ta-ctn,
    html:not(.dark) .fi-wi-stats-overview-stat { border-radius: .875rem; box-shadow: 0 1px 2px rgba(10, 34, 80, .05); background-color: #fff; }
    html:not(.dark) .fi-header-heading { font-weight: 700; letter-spacing: -.01em; }
    html:not(.dark) .fi-header-subheading { color: #5b6783; }
    html:not(.dark) .fi-breadcrumbs-item-label { color: #5b6783; font-size: .8125rem; }
    html:not(.dark) .fi-section-header-heading { font-size: 1rem; font-weight: 600; }
    html:not(.dark) .fi-section-header-description { color: #5b6783; }
    html:not(.dark) .fi-wi-stats-overview-stat-label { color: #5b6783; font-weight: 500; }
    html:not(.dark) .fi-wi-stats-overview-stat-value { color: #0a2250; font-weight: 700; letter-spacing: -.02em; }
    html:not(.dark) .fi-wi-stats-overview-stat-description { color: #6b7691; }
    html:not(.dark) .fi-ta-header-cell { background-color: #faf7f0; color: #4a5873; font-size: .75rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; }
    html:not(.dark) .fi-ta-row:hover { background-color: #f7f9fd; }
    html:not(.dark) .fi-ta-cell, html:not(.dark) .fi-ta-header-cell { padding-block: .65rem; }
    html:not(.dark) .fi-ta-header { border-bottom: 1px solid #ece6d6; }
    html:not(.dark) .fi-sidebar-item-btn { border-radius: .5rem; }
    html:not(.dark) .fi-sidebar-item.fi-active .fi-sidebar-item-label { color: #0a2250; font-weight: 600; }
    html:not(.dark) .fi-sidebar-group-label { margin-top: .25rem; }
    html:not(.dark) .fi-btn { border-radius: .5rem; font-weight: 500; }
    html:not(.dark) .fi-badge { border-radius: .375rem; font-weight: 500; }
    html:not(.dark) .fi-input-wrp, html:not(.dark) .fi-select-input, html:not(.dark) .fi-fo-field-wrp .fi-input-wrp { border-radius: .5rem; }
    html:not(.dark) .fi-main { padding-block: 1.5rem; }
    /* Tables on a phone: keep the page itself from scrolling sideways, let the table scroll inside its box. */
    .fi-ta-content { overflow-x: auto; }
    @media (max-width: 640px) {
        .fi-main { padding-inline: .75rem; }
        .fi-header-heading { font-size: 1.25rem; }
    }
    @media (prefers-reduced-motion: reduce) { .fi-body * { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
</style>
