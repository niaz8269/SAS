<?php

return [
    'brand' => [
        // The owner's spelling is intentional and must be preserved.
        'name' => 'SAS – Scholarships Application Services',
        'short' => 'SAS',
        'tagline' => 'Turning Potential into Admission',
    ],

    // Owner decisions still open (docs/DECISIONS.md). Shown in the footer until confirmed.
    'legal_entity' => env('SAS_LEGAL_ENTITY', 'Operating legal entity: to be confirmed'),
    'contact_email' => env('SAS_CONTACT_EMAIL', 'hello@example.com'),
    'accessibility_email' => env('SAS_ACCESSIBILITY_EMAIL', 'accessibility@example.com'),
    'staff_notification_email' => env('SAS_STAFF_NOTIFICATION_EMAIL'),
    // Shared secret for the signed email-delivery webhook. Empty = endpoint disabled.
    'mail_webhook_secret' => env('SAS_MAIL_WEBHOOK_SECRET'),

    /*
     * Role => permissions (AUTH-05: publish, refund and role-management are separately grantable).
     * Permission names contain a dot; App\Providers\AuthServiceProvider resolves them through the Gate.
     */
    'permissions' => [
        'super_admin' => ['*'],
        'administrator' => [
            'content.draft', 'content.override', 'import.stage', 'users.manage', 'audit.view', 'reports.view',
            'enquiries.view_all', 'enquiries.assign', 'services.manage', 'articles.manage', 'taxonomy.manage', 'corrections.manage', 'site.manage',
        ],
        'service_manager' => ['services.manage', 'enquiries.view_all', 'enquiries.assign', 'reports.view'],
        'advisor' => ['enquiries.assigned', 'consultations.assigned'],
        'specialist' => ['enquiries.assigned'],
        'editor' => ['content.draft', 'import.stage', 'articles.manage', 'taxonomy.manage', 'corrections.manage'],
        'reviewer' => ['content.draft', 'content.review', 'content.publish', 'articles.manage', 'taxonomy.manage', 'corrections.manage'],
        'finance' => ['finance.view'], // R2
        'partner_manager' => ['partner.submit'], // R2
    ],
    'staff_roles' => ['super_admin', 'administrator', 'service_manager', 'advisor', 'specialist', 'editor', 'reviewer', 'finance'],

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
        'images/hero-graduates.jpg' => ['name' => 'Pang Yuhao', 'url' => 'https://unsplash.com/photos/people-throwing-hats-on-air-_kd5cxwZOK4', 'subject' => 'Graduates throwing their caps'],
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
