<?php

namespace App\Domain\Site;

use Illuminate\Support\Facades\DB;

/** The site's original wording and navigation, stored as ordinary rows so staff can edit, reorder, hide or delete them. */
class SiteDefaults
{
    /** Built-in blocks of the list pages and the contact page. Built-ins can be edited or hidden; staff can add more. */
    public static function seedPageBlocks(): void
    {
        $now = now();
        $blocks = [
            ['directory', 'intro', 'Directory: heading and introduction', 'Scholarship directory',
                'Every listing links to its official source and shows when it was last checked. This is a curated selection, not every scholarship in the world.',
                'Search officially sourced scholarships by destination, degree, subject, funding and deadline. No account needed.'],
            ['destinations', 'intro', 'Destinations: heading and introduction', 'Study destinations',
                'Each guide shows its sources and the date it was last reviewed. Visa and entry rules change: always confirm with the official authority.',
                'Study destination guides with costs, official sources and the scholarships available there.'],
            ['destinations', 'empty', 'Destinations: message when none are published', null,
                'Destination guides will appear here once they have been reviewed and published.', null],
            ['services', 'intro', 'Services: heading and introduction', 'Support beyond the scholarship',
                'Professional help at each step. Every service starts with a free enquiry; we confirm scope, deliverables and any fee with you before work begins. No service guarantees scholarship selection, admission, attestation acceptance or a visa decision.',
                'Advisory, writing, document evaluation, translation, attestation, admissions and visa support for international students.'],
            ['services', 'empty', 'Services: message when none are published', null, 'Service pages will appear here once published.', null],
            ['resources', 'intro', 'Resources: heading and introduction', 'Resources', 'Practical guides for international students.',
                'Guides and articles on scholarships, applications and studying abroad.'],
            ['resources', 'empty', 'Resources: message when none are published', null, 'Guides will appear here once they are published.', null],
            ['contact', 'intro', 'Contact: heading and introduction', 'Contact us',
                'Ask a question or tell us what help you need. Enquiries are free and never create a charge. Please do not include passport numbers or other sensitive details here.',
                'Send an enquiry to the team. We reply by email or phone as you prefer.'],
            ['contact', 'aside', 'Contact: side box', 'Other ways to reach us',
                'If the form does not work for you, email us and we will reply.', null],
        ];
        foreach ($blocks as $i => [$page, $key, $label, $heading, $body, $meta]) {
            DB::table('page_blocks')->insert(['page' => $page, 'key' => $key, 'label' => $label, 'position' => 'top', 'enabled' => true, 'sort' => ($i + 1) * 10,
                'heading' => $heading, 'body' => $body, 'subheading' => $meta, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('page_blocks')->where(['page' => 'contact', 'key' => 'aside'])->update(['cta_label' => 'Request a consultation', 'cta_url' => '/consultation']);
    }

    public static function seed(): void
    {
        $now = now();

        $menus = [
            'header' => [['Home', '/'], ['Scholarships', '/scholarships'], ['Study Destinations', '/destinations'], ['Our Services', '/services'],
                ['Resources', '/resources'], ['About', '/about'], ['Contact', '/contact']],
            'footer_explore' => [['Scholarships', '/scholarships'], ['Study destinations', '/destinations'], ['Our services', '/services'],
                ['Resources', '/resources']],
            'footer_policies' => [['Privacy policy', '/legal/privacy'], ['Terms and conditions', '/legal/terms'], ['Cancellation and refunds', '/legal/refunds'],
                ['Accessibility', '/legal/accessibility'], ['Accuracy and corrections', '/corrections/new']],
        ];
        foreach ($menus as $location => $items) {
            foreach ($items as $i => [$label, $url]) {
                DB::table('menu_items')->insert(['location' => $location, 'label' => $label, 'url' => $url, 'new_tab' => false, 'visible' => true,
                    'sort' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $sections = [
            ['hero', 'Hero and search box', 'Global scholarships. Brighter futures.', "Your ambition.\nA world of possibilities.",
                'Search scholarships worldwide', 'Verified scholarship opportunities drawn from official sources, with professional guidance from your first enquiry through to admission.',
                'Explore Scholarships', '/scholarships', 'Our services', '/services', [
                    ['title' => 'Explore opportunities', 'text' => 'Search by destination, degree and subject.'],
                    ['title' => 'Get expert guidance', 'text' => 'Advisory and application support.'],
                    ['title' => 'Take the next step', 'text' => 'Plan each application and track every step.'],
                ]],
            ['degrees', 'Browse by degree', null, 'Browse by degree', null, null, null, null, null, null, null],
            ['featured', 'Featured scholarships', 'Featured opportunities', 'Find your next opportunity', null, null, 'View all scholarships', '/scholarships', null, null, null],
            ['closing_soon', 'Closing soon', null, 'Closing soon', null,
                'Only calls with a confirmed upcoming closing date are listed. Dates can change: ask us for the latest information.',
                'View upcoming deadlines', '/scholarships?sort=closing', null, null, null],
            ['destinations', 'Study destinations', null, 'Where will your future take you?', 'Discover study destinations and the scholarships available there.', null,
                'Explore all destinations', '/destinations', null, null, null],
            ['services', 'Services', null, 'Support beyond the scholarship', 'Professional help at every step of your study abroad journey.', null,
                'Learn more about our services', '/services', null, null, null],
            ['how', 'How the platform works', null, 'How the platform works', null, null, null, null, null, null, [
                ['title' => 'Discover', 'text' => 'Search verified scholarship calls. Every call shows its official source and when it was last checked.'],
                ['title' => 'Plan', 'text' => 'Save scholarships, build an application checklist and keep documents in a private vault, and ask our team for support.'],
                ['title' => 'Get support', 'text' => 'Ask for advice, writing help, document evaluation, attestation or visa guidance when you need it.'],
            ]],
            ['resources', 'Latest resources', null, 'Latest resources', null, null, 'All guides', '/resources', null, null, null],
            ['consultation', 'Consultation banner', null, "Let's plan your next chapter.", null,
                'Request a one-on-one consultation. Our team will confirm a time with you. A request is not a confirmed booking.',
                'Book a Consultation', '/consultation', null, null, null],
        ];
        foreach ($sections as $i => [$key, $label, $eyebrow, $heading, $sub, $body, $cta, $ctaUrl, $cta2, $cta2Url, $items]) {
            DB::table('home_sections')->insert(['key' => $key, 'label' => $label, 'enabled' => true, 'sort' => ($i + 1) * 10, 'eyebrow' => $eyebrow,
                'heading' => $heading, 'subheading' => $sub, 'body' => $body, 'cta_label' => $cta, 'cta_url' => $ctaUrl, 'cta2_label' => $cta2,
                'cta2_url' => $cta2Url, 'items' => $items ? json_encode($items) : null, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
