<?php

return [
    'brand' => [
        // The owner's spelling is intentional and must be preserved.
        'name' => 'SAS – Scholorship Application Services',
        'short' => 'SAS',
        'tagline' => 'Global scholarships. Brighter futures.',
    ],

    // Owner decisions still open (docs/DECISIONS.md). Shown in the footer until confirmed.
    'legal_entity' => env('SAS_LEGAL_ENTITY', 'Operating legal entity: to be confirmed'),
    'contact_email' => env('SAS_CONTACT_EMAIL', 'hello@example.com'),
    'accessibility_email' => env('SAS_ACCESSIBILITY_EMAIL', 'accessibility@example.com'),
    'staff_notification_email' => env('SAS_STAFF_NOTIFICATION_EMAIL'),

    /*
     * Role => permissions (AUTH-05: publish, refund and role-management are separately grantable).
     * Permission names contain a dot; App\Providers\AuthServiceProvider resolves them through the Gate.
     */
    'permissions' => [
        'super_admin' => ['*'],
        'administrator' => [
            'content.draft', 'content.override', 'import.stage', 'users.manage', 'audit.view', 'reports.view',
            'enquiries.view_all', 'enquiries.assign', 'services.manage', 'articles.manage', 'taxonomy.manage', 'corrections.manage',
        ],
        'service_manager' => ['services.manage', 'enquiries.view_all', 'enquiries.assign', 'reports.view'],
        'advisor' => ['enquiries.assigned', 'consultations.assigned'],
        'specialist' => ['enquiries.assigned'],
        'editor' => ['content.draft', 'import.stage', 'articles.manage', 'taxonomy.manage', 'corrections.manage'],
        'reviewer' => ['content.draft', 'content.review', 'content.publish', 'articles.manage', 'corrections.manage'],
        'finance' => ['finance.view'], // R2
        'partner_manager' => ['partner.submit'], // R2
    ],
    'staff_roles' => ['super_admin', 'administrator', 'service_manager', 'advisor', 'specialist', 'editor', 'reviewer', 'finance'],

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
