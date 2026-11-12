<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wording for the list pages that says what the page is in the words people search with (see docs/KEYWORDS.md).
 * A block is rewritten only while its text is still the original one: a block that staff have edited in the Admin is never touched.
 * Everything stated is already true of the pages (nothing about funding or results is promised).
 */
return new class extends Migration
{
    public function up(): void
    {
        $blocks = [
            // [page, key, original body, new heading, new body, new subheading]
            ['directory', 'intro', 'Every listing links to its official source and shows when it was last checked. This is a curated selection, not every scholarship in the world.',
                'Scholarship directory: scholarships for international students',
                'Search scholarships to study abroad by destination, degree level, subject, funding and deadline. Every listing shows who can apply, the documents required and the deadline, links to its official source and shows when it was last checked. This is a curated selection, not every scholarship in the world.',
                'Search scholarships for international students by destination, degree, subject, funding and deadline. Each listing shows who can apply, the documents required and its official source.'],
            ['destinations', 'intro', 'Each guide shows its sources and the date it was last reviewed. Visa and entry rules change: always confirm with the official authority.',
                'Study destinations: country guides for studying abroad',
                'Country guides for studying abroad, with costs, how admission works and the scholarships SAS lists for each country. Each guide shows its sources and the date it was last reviewed. Visa and entry rules change: always confirm with the official authority.',
                'Study abroad guides with costs, official sources and the scholarships available in each country.'],
            ['services', 'intro', 'Professional help at each step. Every service starts with a free enquiry; we confirm scope, deliverables and any fee with you before work begins. No service guarantees scholarship selection, admission, attestation acceptance or a visa decision.',
                'Study abroad services: support beyond the scholarship',
                'Professional help at each step of a study abroad application: scholarship application help, admission assistance, statement of purpose and motivation letter writing, document translation and attestation, and student visa support. Every service starts with a free enquiry; we confirm scope, deliverables and any fee with you before work begins. No service guarantees scholarship selection, admission, attestation acceptance or a visa decision.',
                'Study abroad services: scholarship application help, admission assistance, document translation and attestation, and student visa support.'],
            ['resources', 'intro', 'Practical guides for international students.',
                'Resources: scholarship guides and study abroad advice',
                'Practical guides for international students on scholarships, applications, required documents and studying abroad.',
                'Guides and articles on scholarships, applications and studying abroad.'],
            ['universities', 'intro', 'Universities listed on the official study-in-China system for international students. Programme lists, tuition and scholarship eligibility are copied from that system and change every year: always confirm with the university and the official scholarship pages.',
                'Universities in China for international students',
                'Universities in China listed on the official study-in-China system for international students, with the programmes they list in English and in Chinese, the listed tuition and which programmes are open to Chinese Government Scholarship applicants. Programme lists, tuition and scholarship eligibility are copied from that system and change every year: always confirm with the university and the official scholarship pages.',
                'Browse universities in China that accept international students, with their programmes (including programmes taught in English), tuition and teaching languages.'],
            ['programmes', 'intro', 'Find degree programmes at universities in China by subject, degree level, teaching language and province. The star marks programmes the official system lists as open to Chinese Government Scholarship applicants; check the current call before you rely on it.',
                'Search programmes: degree programmes at universities in China',
                null, null],
        ];
        foreach ($blocks as [$page, $key, $original, $heading, $body, $sub]) {
            $update = ['heading' => $heading, 'updated_at' => now()] + ($body !== null ? ['body' => $body] : []) + ($sub !== null ? ['subheading' => $sub] : []);
            DB::table('page_blocks')->where(['page' => $page, 'key' => $key, 'body' => $original])->update($update);
        }

        // The home page description is the hero text: say who the scholarships are for, only while it is still the original sentence.
        DB::table('home_sections')->where(['key' => 'hero', 'body' => 'Verified scholarship opportunities drawn from official sources, with professional guidance from your first enquiry through to admission.'])
            ->update(['body' => 'Verified scholarships for international students, drawn from official sources, with professional guidance from your first enquiry through to admission.', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Not reversed: the original wording is stored in earlier migrations and staff may have edited the text since.
    }
};
