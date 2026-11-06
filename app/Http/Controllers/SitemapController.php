<?php

namespace App\Http\Controllers;

use App\Domain\Seo\Sitemap;

/** The XML sitemaps for search engines, and the same list as a page for visitors. See App\Domain\Seo\Sitemap. */
class SitemapController extends Controller
{
    public function index(Sitemap $sitemap)
    {
        return response($sitemap->indexXml(), 200, ['Content-Type' => 'application/xml']);
    }

    public function section(Sitemap $sitemap, string $section)
    {
        abort_unless(array_key_exists($section, Sitemap::SECTIONS), 404);

        return response($sitemap->sectionXml($section), 200, ['Content-Type' => 'application/xml']);
    }

    /** Every public page on one list. Visitors use it, and it gives search engines a link to every page that is one step from the footer. */
    public function page(Sitemap $sitemap)
    {
        return view('public.site-map', ['sections' => $sitemap->all()]);
    }
}
