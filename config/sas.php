<?php

return [
    'brand' => [
        // The owner's spelling is intentional and must be preserved.
        'name' => 'SAS – Scholarships Application Services',
        'short' => 'SAS',
        'tagline' => 'Turning Potential into Admission',
    ],

    // Owner decisions still open (docs/DECISIONS.md). Shown in the footer until confirmed.
    'legal_entity_name' => env('SAS_LEGAL_ENTITY_NAME', 'CAS - Consultancy for Abroad Study'),
    'legal_entity' => env('SAS_LEGAL_ENTITY', 'Operated by CAS - Consultancy for Abroad Study'),
    // Set SAS_LEGAL_APPROVED=true once the operating entity or counsel has approved the privacy, terms and refund texts; it hides the draft notice on those pages.
    'legal_approved' => (bool) env('SAS_LEGAL_APPROVED', false),

    // Service fees in US dollars, shown on the Services, Terms and Refund pages. The application fee is charged per scholarship application;
    // part of it is refunded if the application is rejected; the agency service charge is due only after acceptance.
    'fees' => [
        'currency' => 'US$',
        'application' => (int) env('SAS_FEE_APPLICATION', 100),
        'application_refundable' => (int) env('SAS_FEE_APPLICATION_REFUNDABLE', 50),
        'agency_service' => (int) env('SAS_FEE_AGENCY_SERVICE', 500),
    ],

    // Contact details (editable in the admin under Website, Site settings; these are the defaults).
    'contact_email' => env('SAS_CONTACT_EMAIL', 'info@sas.cas.moe'),
    'enquiry_email' => env('SAS_ENQUIRY_EMAIL', 'enquiry@sas.cas.moe'),
    'admissions_email' => env('SAS_ADMISSIONS_EMAIL', 'admissions@sas.cas.moe'),
    'accessibility_email' => env('SAS_ACCESSIBILITY_EMAIL', 'info@sas.cas.moe'),
    'contact_phone' => env('SAS_CONTACT_PHONE', '+86 176 4041 2827'),
    // Live chat widget (tawk.to) loaded on the public pages only. Empty turns it off. Never loaded on signed-in or admin pages, where a third-party script could read personal data.
    'tawk_embed' => env('SAS_TAWK_EMBED', 'https://embed.tawk.to/6adcff3f803f96347af46f30/1k5nka51i'),
    'contact_whatsapp' => env('SAS_CONTACT_WHATSAPP', '+86 176 4041 2827'),
    'contact_wechat' => env('SAS_CONTACT_WECHAT', 'cas-scholarships'),
    // New-enquiry alerts go to the enquiry mailbox.
    'staff_notification_email' => env('SAS_STAFF_NOTIFICATION_EMAIL', 'enquiry@sas.cas.moe'),
    // Shared secret for the signed email-delivery webhook. Empty = endpoint disabled.
    'mail_webhook_secret' => env('SAS_MAIL_WEBHOOK_SECRET'),

    /*
     * Role => permissions (AUTH-05: publish, refund and role-management are separately grantable).
     * Permission names contain a dot; App\Providers\AuthServiceProvider resolves them through the Gate.
     */
    'permissions' => [
        // The owner: everything, including who holds which role.
        'super_admin' => ['*'],
        // Runs the service and the website: content (draft, review, publish), imports, articles, reference data, the website itself, enquiries,
        // services, cases, fees and payments, agents, document rules, students and counsellors, reports and users. Can also counsel students.
        'administrator' => [
            'content.draft', 'content.review', 'content.publish', 'content.override', 'import.stage', 'users.manage', 'audit.view', 'reports.view',
            'enquiries.view_all', 'enquiries.assign', 'services.manage', 'articles.manage', 'taxonomy.manage', 'corrections.manage', 'site.manage',
            'cases.manage', 'finance.view', 'invoices.manage', 'partners.manage', 'documents.manage', 'students.manage',
            'enquiries.assigned', 'consultations.assigned', 'cases.assigned', 'students.assigned',
        ],
        // Looks after assigned students, enquiries, consultations and cases.
        'advisor' => ['enquiries.assigned', 'consultations.assigned', 'cases.assigned', 'students.assigned'],
        'partner_manager' => [], // a recruiting agent's access is decided by their organisation and by policies, not by a role permission
    ],
    /*
     * How long before the Needs attention list and the admin alert email call something late. Change them in .env.
     */
    'sla' => [
        'enquiry_hours' => (int) env('SAS_SLA_ENQUIRY_HOURS', 24), // no first reply to a new enquiry
        'reply_hours' => (int) env('SAS_SLA_REPLY_HOURS', 48), // a student's message nobody answered
        'review_days' => (int) env('SAS_SLA_REVIEW_DAYS', 3), // an application waiting for SAS review
        'returned_days' => (int) env('SAS_SLA_RETURNED_DAYS', 7), // sent back and still not fixed
        'unpaid_days' => (int) env('SAS_SLA_UNPAID_DAYS', 7), // an agency charge issued and still unpaid
    ],

    'staff_roles' => ['super_admin', 'administrator', 'advisor'],

    // Bundled photographs (Unsplash licence: free to use; credit is given as a courtesy). Shown on /image-credits.
    'image_credits' => [
        'images/destinations/cn.jpg' => ['name' => 'William Olivieri', 'url' => 'https://unsplash.com/photos/great-wall-of-china-on-mountains-HwY9R6_YzgM', 'subject' => 'The Great Wall of China'],
        'images/destinations/de.jpg' => ['name' => 'Arlind Photography', 'url' => 'https://unsplash.com/photos/brandenburg-gate-in-berlin-on-a-sunny-day-ot2mgzwyqLg', 'subject' => 'Brandenburg Gate, Berlin'],
        'images/destinations/tr.jpg' => ['name' => 'Lewis J Goetz', 'url' => 'https://unsplash.com/photos/hagia-sophia-in-istanbul-JbWg7W953LY', 'subject' => 'Hagia Sophia, Istanbul'],
        'images/destinations/gb.jpg' => ['name' => 'Marcin Nowak', 'url' => 'https://unsplash.com/photos/big-ben-london-iXqTqC-f6jI', 'subject' => 'Big Ben and the Houses of Parliament, London'],
        'images/destinations/default.jpg' => ['name' => 'Andrew Stutesman', 'url' => 'https://unsplash.com/photos/blue-green-and-yellow-world-map-l68Z6eF2peA', 'subject' => 'World map with pins'],
        'images/destinations/kr.jpg' => ['name' => 'Ping Onganankun', 'url' => 'https://unsplash.com/photos/crescent-moon-over-seoul-skyline-5htrsUUbFGI', 'subject' => 'Seoul skyline at dusk'],
        'images/destinations/jp.jpg' => ['name' => 'Louie Martinez', 'url' => 'https://unsplash.com/photos/tokyo-tower-illuminated-at-dusk-with-city-skyline-IocJwyqRv3M', 'subject' => 'Tokyo Tower and the city at dusk'],
        'images/destinations/us.jpg' => ['name' => 'Triston Dunn', 'url' => 'https://unsplash.com/photos/empire-state-building-new-york-city-during-daytime-rfj_wOYQkus', 'subject' => 'Empire State Building, New York'],
        'images/destinations/nz.jpg' => ['name' => 'Andreas Sjövall', 'url' => 'https://unsplash.com/photos/landscape-photo-of-mountains-near-body-of-water-during-daytime-ZZUPu8ARD8U', 'subject' => 'Mountains and lake, New Zealand'],
        'images/destinations/ch.jpg' => ['name' => 'LJ Coates', 'url' => 'https://unsplash.com/photos/green-trees-on-mountain-beside-body-of-water-under-blue-sky-during-daytime-vJDqJ6o6Haw', 'subject' => 'Swiss lake and Alps'],
        'images/cities/beijing.jpg' => ['name' => 'Victor He', 'url' => 'https://unsplash.com/photos/temple-of-heaven-in-beijing-0xn9T2cEigE', 'subject' => 'Temple of Heaven, Beijing'],
        'images/cities/shanghai.jpg' => ['name' => 'Edward He', 'url' => 'https://unsplash.com/photos/orient-pearl-shanghai-china-taken-during-daytime-uKyzXEc2k_s', 'subject' => 'Pudong skyline, Shanghai'],
        'images/cities/guangdong.jpg' => ['name' => 'Zheng XUE', 'url' => 'https://unsplash.com/photos/city-skyline-with-modern-skyscrapers-at-sunset-RBxAme1dn6E', 'subject' => 'Guangzhou skyline'],
        'images/cities/guangxi.jpg' => ['name' => 'Sergio Li', 'url' => 'https://unsplash.com/photos/a-person-looking-out-over-li-river-HljGrgrrvxc', 'subject' => 'Li River, Guangxi'],
        'images/cities/zhejiang.jpg' => ['name' => 'Luobing', 'url' => 'https://unsplash.com/photos/a-building-sitting-on-top-of-a-lake-next-to-a-forest-42Ah4zCgT-w', 'subject' => 'Lakeside pavilion, Hangzhou'],
        'images/cities/shaanxi.jpg' => ['name' => 'Yux Xiang', 'url' => 'https://unsplash.com/photos/the-sun-is-setting-over-the-city-skyline-zvVX7prwg4c', 'subject' => 'Xi\'an city wall'],
        'images/cities/chongqing.jpg' => ['name' => 'Paz ifical', 'url' => 'https://unsplash.com/photos/a-city-skyline-at-night-with-a-bridge-in-the-foreground--bOqztU8ndI', 'subject' => 'Chongqing at night'],
        'images/cities/tianjin.jpg' => ['name' => 'Zihan Wong', 'url' => 'https://unsplash.com/photos/a-city-skyline-with-a-ferris-wheel-in-the-foreground-Kzx_xZl8FT4', 'subject' => 'Tianjin Eye and the Hai River'],
        'images/destinations/ae.jpg' => ['name' => 'David Rodrigo', 'url' => 'https://unsplash.com/photos/sheikh-zayed-grand-mosque-in-abu-dhabi-kZ1zThg6G40', 'subject' => 'Sheikh Zayed Grand Mosque, Abu Dhabi'],
        'images/destinations/at.jpg' => ['name' => 'Jacek Dylag', 'url' => 'https://unsplash.com/photos/st-stephens-cathedral-skyline-in-vienna-5SjAaqqCCmY', 'subject' => 'Vienna skyline with St. Stephen\'s Cathedral'],
        'images/destinations/az.jpg' => ['name' => 'Adil Sattarov', 'url' => 'https://unsplash.com/photos/a-group-of-tall-buildings-sitting-next-to-each-other-If5euq-ktso', 'subject' => 'Flame Towers, Baku'],
        'images/destinations/cz.jpg' => ['name' => 'William Zhang', 'url' => 'https://unsplash.com/photos/charles-bridge-and-prague-castle-6En4WYsNYXM', 'subject' => 'Charles Bridge and Prague Castle'],
        'images/destinations/es.jpg' => ['name' => 'Jorge Fernández Salas', 'url' => 'https://unsplash.com/photos/edificio-metropoli-on-gran-via-madrid-ChSZETOal-I', 'subject' => 'Gran Vía, Madrid'],
        'images/destinations/fr.jpg' => ['name' => 'Chris Karidis', 'url' => 'https://unsplash.com/photos/eiffel-tower-paris-france-nnzkZNYWHaU', 'subject' => 'Eiffel Tower, Paris'],
        'images/destinations/hu.jpg' => ['name' => 'Ervin Lukacs', 'url' => 'https://unsplash.com/photos/hungarian-parliament-building-in-budapest-sMyQb3i9bNA', 'subject' => 'Hungarian Parliament, Budapest'],
        'images/destinations/it.jpg' => ['name' => 'David Köhler', 'url' => 'https://unsplash.com/photos/colosseum-arena-photography-VFRTXGw1VjU', 'subject' => 'The Colosseum, Rome'],
        'images/destinations/kz.jpg' => ['name' => 'Viktor Hesse', 'url' => 'https://unsplash.com/photos/a-city-skyline-with-a-fountain-JvQCPAtNMGw', 'subject' => 'Bayterek Tower and skyline, Astana'],
        'images/destinations/nl.jpg' => ['name' => 'Adrien Olichon', 'url' => 'https://unsplash.com/photos/boats-on-canal-in-amsterdam-QRtym77B6xk', 'subject' => 'Canal in Amsterdam'],
        'images/destinations/om.jpg' => ['name' => 'Anfal Shamsudeen', 'url' => 'https://unsplash.com/photos/al-lawatiya-mosque-in-muscat-oman-dZd4XWMiM9g', 'subject' => 'Al Lawatiya Mosque, Muscat'],
        'images/destinations/pl.jpg' => ['name' => 'Maksym Harbar', 'url' => 'https://unsplash.com/photos/people-walking-near-multicolored-building-okn8ZIjPMxI', 'subject' => 'Old Town, Warsaw'],
        'images/destinations/qa.jpg' => ['name' => 'Popup Agency', 'url' => 'https://unsplash.com/photos/katara-towers-in-lusail-qatar-jJtqDVzozQY', 'subject' => 'Katara Towers, Lusail, Qatar'],
        'images/destinations/ru.jpg' => ['name' => 'Dmitry Ant', 'url' => 'https://unsplash.com/photos/a-large-building-with-a-clock-tower-KXCRqEHWM_A', 'subject' => 'The Kremlin and Red Square, Moscow'],
        'images/destinations/sa.jpg' => ['name' => 'Ibrahim Abdullah', 'url' => 'https://unsplash.com/photos/kingdom-centre-skyscraper-in-riyadh-_UxsPARhtOg', 'subject' => 'Kingdom Centre, Riyadh'],
        'images/destinations/se.jpg' => ['name' => 'Ana Bórquez', 'url' => 'https://unsplash.com/photos/white-boat-on-water-near-city-buildings-during-daytime-uF4PfwZPOR8', 'subject' => 'Riddarholmen, Stockholm'],
        'images/destinations/uz.jpg' => ['name' => 'Aleksander Stypczynski', 'url' => 'https://unsplash.com/photos/a-large-building-with-many-pillars-and-arches-l3AwHYiUGG0', 'subject' => 'The Registan, Samarkand'],
        'images/hero-shanghai.jpg' => ['name' => 'Edward He', 'url' => 'https://unsplash.com/photos/orient-pearl-shanghai-china-taken-during-daytime-uKyzXEc2k_s', 'subject' => 'Shanghai skyline (homepage banner)'],
        'images/hero-globe.webp' => ['name' => 'Arpit Rastogi', 'url' => 'https://unsplash.com/photos/white-and-brown-desk-globe-xv7DTjnx2YQ', 'subject' => 'Desk globe (homepage banner, cut out)'],
    ],

    'deadlines' => [
        'reminder_days' => [30, 14, 7, 1], // NOT-01
        'closing_soon_days' => 30,
    ],

    // DATA-04 (configurable operating defaults)
    'review' => [
        'cadence_days' => 30,
        'cadence_days_closing_soon' => 7,
    ],

    /*
     * Two-step sign-in (a 6-digit code from an authenticator app). Off by default: no login page asks for a code, staff included.
     * Turn it on with SAS_TWO_STEP=true: staff must then set it up, and students may opt in on their Security page.
     */
    'two_step' => ['enabled' => (bool) env('SAS_TWO_STEP', false)],

    // Invitation emails an agency may send per day (an agent account must not become a mail relay), and the host names the site answers to.
    'agents' => ['invites_per_day' => (int) env('SAS_AGENT_INVITES_PER_DAY', 200)],
    'trusted_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('SAS_TRUSTED_HOSTS', ''))))),

    'uploads' => [
        'max_kb' => (int) env('SAS_UPLOAD_MAX_KB', 20480), // FILE-01: 20 MB
        'quota_mb' => (int) env('SAS_UPLOAD_QUOTA_MB', 200), // proposed, pending owner approval
        'extensions' => ['pdf', 'docx', 'jpg', 'jpeg', 'png'],
        'mimes' => [
            'pdf' => ['application/pdf'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
        ],
        'download_ttl_minutes' => 5, // SEC-03
        'disk' => env('SAS_DOCUMENT_DISK', 'private_documents'),
        'scanner' => env('SAS_SCANNER', 'clamav'), // clamav | fake (tests only)
        'clamav_host' => env('CLAMAV_HOST', '127.0.0.1'),
        'clamav_port' => (int) env('CLAMAV_PORT', 3310),
    ],

    // PRIV-03 proposed retention defaults, flagged pending owner / jurisdiction approval.
    'retention' => [
        'approved' => false,
        'abandoned_upload_days' => 30,
        'support_draft_days' => 90,
        'case_document_days_after_closure' => 90,
    ],

    'document_types' => [
        'passport' => 'Passport',
        'transcript' => 'Transcript',
        'degree_certificate' => 'Degree certificate',
        'cv' => 'CV',
        'statement' => 'Statement / motivation letter',
        'language_certificate' => 'Language certificate',
        'recommendation' => 'Recommendation letter',
        'other' => 'Other',
    ],

    'enquiry_categories' => [
        'general' => 'General question',
        'advisory' => 'Scholarship advisory',
        'writing' => 'Writing and applications',
        'document_evaluation' => 'Document evaluation',
        'translation' => 'Translation',
        'attestation' => 'Attestation',
        'admissions' => 'University admissions',
        'visa' => 'Visa assistance',
        'pre_departure' => 'Pre-departure',
        'correction' => 'Report a correction',
    ],
];
