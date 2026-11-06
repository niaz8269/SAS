<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\CmsPage;
use App\Models\Country;
use App\Models\MediaFile;
use App\Models\ScholarshipCycle;
use App\Models\Service;
use App\Models\University;

/** Public editorial pages: destinations, services, resources and static pages. */
class PageController extends Controller
{
    public function destinations()
    {
        return view('public.destinations.index', [
            'countries' => Country::destinations()->where('guidance_published', true)->get(),
        ]);
    }

    public function destination(Country $country)
    {
        abort_unless($country->is_destination && $country->guidance_published, 404);

        $cycles = ScholarshipCycle::visible()->whereHas('destinations', fn ($q) => $q->where('countries.id', $country->id))
            ->where('application_status', '!=', 'closed')
            ->with(['programme.funder', 'destinations', 'degreeLevels', 'currentDeadlines', 'universities', 'programme.universities'])
            ->orderByRaw('next_closing_sort IS NULL')->orderBy('next_closing_sort')->limit(6)->get();

        return view('public.destinations.show', [
            'country' => $country, 'cycles' => $cycles,
            'services' => Service::published()->limit(3)->get(),
        ]);
    }

    public function services()
    {
        return view('public.services.index', ['services' => Service::published()->get()]);
    }

    public function service(Service $service)
    {
        abort_unless($service->published, 404);

        return view('public.services.show', ['service' => $service]);
    }

    public function resources()
    {
        return view('public.resources.index', ['articles' => Article::published()->paginate(9)]);
    }

    public function resource(Article $article)
    {
        abort_unless($article->status === 'published' && $article->published_at?->isPast(), 404);

        return view('public.resources.show', ['article' => $article]);
    }

    /** A published page written in the admin replaces the built-in page with the same slug. */
    private function cmsOverride(string $slug)
    {
        $page = CmsPage::published()->where('slug', $slug)->first();

        return $page ? view('public.cms-page', ['page' => $page]) : null;
    }

    public function about()
    {
        return $this->cmsOverride('about') ?? view('public.about');
    }

    public function legal(string $page)
    {
        abort_unless(in_array($page, ['privacy', 'terms', 'refunds', 'accessibility'], true), 404);

        return $this->cmsOverride($page) ?? view('public.legal', ['page' => $page]);
    }

    /** Credits for the bundled photographs and for library photos staff have credited. */
    public function credits()
    {
        return view('public.credits', ['bundled' => config('sas.image_credits'), 'cityPhotos' => University::cityCredits(), 'universityPhotos' => University::photoCredits(), 'library' => MediaFile::whereNotNull('credit')->where('credit', '!=', '')->orderBy('name')->get()]);
    }

    public function cms(string $slug)
    {
        return $this->cmsOverride($slug) ?? abort(404);
    }

    public function robots()
    {
        // Private areas, redirects and endless filter combinations stay out of search results; every real page is listed in the sitemap.
        $lines = ['User-agent: *', 'Disallow: /admin', 'Disallow: /student', 'Disallow: /partner', 'Disallow: /review', 'Disallow: /files', 'Disallow: /invoices',
            'Disallow: /go/', 'Disallow: /enquiries/', 'Disallow: /consultation/', 'Disallow: /share-your-story/', 'Disallow: /corrections', 'Disallow: /login', 'Disallow: /register', 'Disallow: /forgot-password',
            // Search and filter combinations are endless and stay out. The numbered pages of a list (?page=2) are real pages and stay open:
            // closed, the universities and scholarships on them could only be found through the sitemap.
            'Disallow: /scholarships?*', 'Allow: /scholarships?page=*', 'Disallow: /universities?*', 'Allow: /universities?page=*', 'Disallow: /programmes?*', 'Allow: /programmes?page=*',
            '', 'Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml'];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }
}
