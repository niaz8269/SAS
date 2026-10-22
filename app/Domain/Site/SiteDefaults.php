<?php

namespace App\Domain\Site;

use Illuminate\Support\Facades\DB;

/** The site's original wording and navigation, stored as ordinary rows so staff can edit, reorder, hide or delete them. */
class SiteDefaults
{
    public static function seed(): void
    {
        $now = now();

        $menus = [
            'header' => [['Home', '/'], ['Scholarships', '/scholarships'], ['Study Destinations', '/destinations'], ['Our Services', '/services'],
                ['Resources', '/resources'], ['About', '/about'], ['Contact', '/contact']],
            'footer_explore' => [['Scholarships', '/scholarships'], ['Study destinations', '/destinations'], ['Our services', '/services'],
                ['Resources', '/resources'], ['For partner organisations', '/partners']],
            'footer_policies' => [['Privacy', '/legal/privacy'], ['Terms', '/legal/terms'], ['Cancellation and refunds', '/legal/refunds'],
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
                'Search scholarships worldwide', 'Discover officially sourced scholarships and get expert support at every step. Browsing is free and never needs an account.',
                'Explore Scholarships', '/scholarships', 'Our services', '/services', [
                    ['title' => 'Explore opportunities', 'text' => 'Search by destination, degree and subject.'],
                    ['title' => 'Get expert guidance', 'text' => 'Advisory and application support.'],
                    ['title' => 'Take the next step', 'text' => 'Plan and track on official portals.'],
                ]],
            ['degrees', 'Browse by degree', null, 'Browse by degree', null, null, null, null, null, null, null],
            ['featured', 'Featured scholarships', 'Featured opportunities', 'Find your next opportunity', null, null, 'View all scholarships', '/scholarships', null, null, null],
            ['closing_soon', 'Closing soon', null, 'Closing soon', null,
                'Only calls with a confirmed upcoming closing date are listed. Always check the official page for the latest information.',
                'View upcoming deadlines', '/scholarships?sort=closing', null, null, null],
            ['destinations', 'Study destinations', null, 'Where will your future take you?', 'Discover study destinations and the scholarships available there.', null,
                'Explore all destinations', '/destinations', null, null, null],
            ['services', 'Services', null, 'Support beyond the scholarship', 'Professional help at every step of your study abroad journey.', null,
                'Learn more about our services', '/services', null, null, null],
            ['how', 'How the platform works', null, 'How the platform works', null, null, null, null, null, null, [
                ['title' => 'Discover', 'text' => 'Search verified scholarship calls. Every call shows its official source and when it was last checked.'],
                ['title' => 'Plan', 'text' => 'Save scholarships, build an application checklist and keep documents in a private vault. You apply on the official website.'],
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
