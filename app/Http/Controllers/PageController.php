<?php

namespace App\Http\Controllers;

use App\Domain\Seo\Landing;
use App\Models\Article;
use App\Models\CmsPage;
use App\Models\Country;
use App\Models\MediaFile;
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
            ->with(['programme.funder', 'destinations', 'degreeLevels', 'currentDeadlines', 'universities', 'programme.universities', 'benefits'])
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

    /**
     * /llms.txt: a short, plain description of the site and its most useful pages for AI assistants (ChatGPT, Claude, Gemini, Perplexity)
     * and their crawlers. Made from the live data, so the counts and links are always current.
     */
    public function llms()
    {
        $text = Cache::remember('llms.txt.'.Cache::get('landing.version', 0), 600, function () {
            $brand = config('sas.brand.name');
            $live = ScholarshipCycle::visible()->count();
            $pages = app(Landing::class)->pages();
            $line = fn (array $p) => '- ['.$p['title'].']('.$p['url'].'): '.$p['count'].' '.($p['count'] === 1 ? 'listing' : 'listings');

            $out = ['# '.$brand, '',
                '> '.$brand.' (SAS) is a directory of '.$live.' scholarships for international students, with deadlines, eligible nationalities, what the funding covers, required documents and the official source of each, plus paid help with applications. Reading the listings is free and needs no account.', '',
                'Each listing is checked against the funder\'s or university\'s own page and shows the date it was last checked. Deadlines are stored with their time zone; a listing whose check is overdue is marked "Needs recheck". "Fully funded" is shown only when tuition and a living allowance are verified as covered.', '',
                '## For students from Pakistan', ''];
            foreach ($pages->where('type', 'pk')->sortBy(fn ($p) => [$p['facet'] === null ? 0 : 1, -$p['count']]) as $p) {
                $out[] = $line($p);
            }
            $out = array_merge($out, ['', '## Guides', '']);
            foreach ($pages->where('type', 'guide') as $p) {
                $out[] = $line($p);
            }
            $out = array_merge($out, ['- [All scholarships, searchable by destination, nationality, degree and subject]('.route('scholarships.index').')', '- [Universities and programmes]('.route('universities.index').')', '- [Study destinations]('.route('destinations.index').')', '', '## Scholarships by destination, degree and funding', '']);
            foreach ($pages->whereIn('type', ['in', 'for', 'funding', 'open'])->sortByDesc('count') as $p) {
                $out[] = $line($p);
            }
            $out = array_merge($out, ['', '## About and help', '',
                '- [How listings are verified]('.route('about').')', '- [Services: scholarship advice, applications, documents, attestation, visas]('.route('services.index').')', '- [Contact]('.route('contact').')', '- [Full site map]('.route('sitemap').')', '']);

            return implode("\n", $out);
        });

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
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
