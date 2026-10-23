<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\CmsPage;
use App\Models\Country;
use App\Models\ScholarshipCycle;
use App\Models\Service;
use App\Models\University;
use Illuminate\Support\Facades\Cache;

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

    public function partners()
    {
        return $this->cmsOverride('partners') ?? view('public.partners');
    }

    public function legal(string $page)
    {
        abort_unless(in_array($page, ['privacy', 'terms', 'refunds', 'accessibility'], true), 404);

        return $this->cmsOverride($page) ?? view('public.legal', ['page' => $page]);
    }

    public function cms(string $slug)
    {
        return $this->cmsOverride($slug) ?? abort(404);
    }

    public function sitemap()
    {
        $xml = Cache::remember('sitemap', 600, function () {
            $urls = collect([route('home'), route('scholarships.index'), route('destinations.index'), route('services.index'),
                route('resources.index'), route('about'), route('contact')]);
            Country::destinations()->where('guidance_published', true)->get()->each(fn ($c) => $urls->push(route('destinations.show', $c)));
            Service::published()->get()->each(fn ($s) => $urls->push(route('services.show', $s)));
            Article::published()->get()->each(fn ($a) => $urls->push(route('resources.show', $a)));
            University::inCatalogue()->get(['slug'])->each(fn ($u) => $urls->push(route('universities.show', $u->slug)));
            $urls->push(route('universities.index'), route('programmes.index'));
            CmsPage::published()->whereNotIn('slug', ['about', 'partners', 'privacy', 'terms', 'refunds', 'accessibility'])->get()->each(fn ($p) => $urls->push(route('pages.show', $p->slug)));
            ScholarshipCycle::visible()->with('programme')->get()->each(fn ($c) => $urls->push(route('scholarships.show', [$c->programme->slug, $c->slug])));

            return view('public.sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots()
    {
        $lines = ['User-agent: *', 'Disallow: /admin', 'Disallow: /student', 'Disallow: /partner', 'Disallow: /go/', 'Disallow: /enquiries/',
            'Disallow: /scholarships?*', 'Disallow: /login', 'Disallow: /register', '', 'Sitemap: '.url('/sitemap.xml')];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }
}
