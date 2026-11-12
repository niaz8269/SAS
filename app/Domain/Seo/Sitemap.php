<?php

namespace App\Domain\Seo;

use App\Models\Article;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\CmsPage;
use App\Models\Country;
use App\Models\ScholarshipCycle;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\University;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The list of public pages for search engines and for the visitor-facing site map. Everything comes from the database, so a page that
 * staff publish appears here by itself and one they take down leaves; saving or deleting any listed record clears the cache (AppServiceProvider).
 *
 * /sitemap.xml is an index of one file per kind of page, so Google Search Console shows how many pages of each kind are indexed.
 * An entry is [address, date the record last changed or null, name for the visitor-facing list]. The date comes from the record and is left out
 * where a page is fixed text; nothing is invented.
 */
class Sitemap
{
    /** file name => heading on the site map page. Order is the order in the index. */
    public const SECTIONS = [
        'pages' => 'Main pages',
        'scholarships' => 'Scholarships',
        'universities' => 'Universities in China',
        'destinations' => 'Study destinations',
        'blog' => 'Blog',
    ];

    private const TTL = 600;

    /** Cache keys a change to published content must clear. */
    public static function cacheKeys(): array
    {
        return array_merge(['sitemap', 'blog.feed', 'landing.pages'], array_map(fn ($s) => 'sitemap.'.$s, array_keys(self::SECTIONS)));
    }

    public static function forget(): void
    {
        Cache::deleteMultiple(self::cacheKeys());
        Cache::forever('landing.version', microtime(true)); // the written text of the landing pages is cached under this version, so a new one makes them rewrite
    }

    /** The XML index that lists one file per section. */
    public function indexXml(): string
    {
        return Cache::remember('sitemap', self::TTL, function () {
            $files = collect(self::SECTIONS)->map(function ($heading, $key) {
                $entries = $this->entries($key);

                return $entries->isEmpty() ? null : [route('sitemap.section', $key), $this->latest($entries)];
            })->filter();

            return view('public.sitemap-index', ['files' => $files])->render();
        });
    }

    public function sectionXml(string $section): string
    {
        return Cache::remember('sitemap.'.$section, self::TTL, fn () => view('public.sitemap', ['urls' => $this->entries($section)])->render());
    }

    /** @return Collection<int, array{0: string, 1: Carbon|null, 2: string}> */
    public function entries(string $section): Collection
    {
        return match ($section) {
            'pages' => $this->pages(),
            'scholarships' => $this->scholarships(),
            'universities' => $this->universities(),
            'destinations' => $this->destinations(),
            'blog' => $this->blog(),
            default => collect(),
        };
    }

    /** Every section for the visitor-facing site map page. */
    public function all(): array
    {
        return collect(self::SECTIONS)->map(fn ($heading, $key) => ['heading' => $heading, 'entries' => $this->entries($key)])->filter(fn ($s) => $s['entries']->isNotEmpty())->all();
    }

    private function latest(Collection $entries): ?Carbon
    {
        return $entries->pluck(1)->filter()->map(fn ($d) => Carbon::parse($d))->max();
    }

    private function pages(): Collection
    {
        $services = Service::published()->get();
        $articles = Article::published()->get();
        $stories = Testimonial::visible()->whereNotNull('story')->whereNotNull('slug')->get(['slug', 'name', 'university', 'updated_at']);
        $cms = CmsPage::published()->whereNotIn('slug', ['about', 'privacy', 'terms', 'refunds', 'accessibility'])->get();

        // A list page changed when the newest thing on it changed.
        $newest = fn (Collection $rows) => $rows->pluck('updated_at')->filter()->max();

        $entries = collect([
            [rtrim(route('home'), '/').'/', null, 'Home'], // the address the home page itself names as canonical
            [route('about'), null, 'About SAS'],
            [route('services.index'), $newest($services), 'Our services'],
            [route('resources.index'), $newest($articles), 'Resources'],
            [route('stories.index'), $newest($stories), 'Student stories'],
            [route('stories.create'), null, 'Share your story'],
            [route('partners.apply'), null, 'Be a partner'],
            [route('contact'), null, 'Contact us'],
            [route('programmes.index'), null, 'Search programmes'],
            [route('legal.show', 'privacy'), null, 'Privacy policy'],
            [route('legal.show', 'terms'), null, 'Terms of use'],
            [route('credits'), null, 'Image credits'],
        ]);
        $services->each(fn ($s) => $entries->push([route('services.show', $s), $s->updated_at, $s->name]));
        $articles->each(fn ($a) => $entries->push([route('resources.show', $a), $a->updated_at, $a->title]));
        $cms->each(fn ($p) => $entries->push([route('pages.show', $p->slug), $p->updated_at, $p->title]));
        $stories->each(fn ($s) => $entries->push([route('stories.show', $s->slug), $s->updated_at, trim($s->name.($s->university ? ': '.$s->university : ''))]));

        return $entries;
    }

    private function scholarships(): Collection
    {
        $cycles = ScholarshipCycle::visible()->with('programme')->get();
        if ($cycles->isEmpty()) {
            return collect(); // a list page with nothing on it is not worth a search engine's visit
        }
        $entries = collect([[route('scholarships.index'), $cycles->pluck('updated_at')->filter()->max(), 'All scholarships']]);
        // Landing pages (by destination, degree level, funding) exist only while they have enough scholarships, so they come and go with the data.
        app(Landing::class)->pages()->each(fn ($p) => $entries->push([$p['url'], $p['updated'] ? Carbon::parse($p['updated']) : null, $p['label']]));
        $cycles->each(fn ($c) => $entries->push([route('scholarships.show', [$c->programme->slug, $c->slug]), $c->updated_at, $c->label()]));

        return $entries;
    }

    private function universities(): Collection
    {
        $unis = University::inCatalogue()->orderBy('name')->get(['slug', 'name', 'updated_at']);
        if ($unis->isEmpty()) {
            return collect();
        }
        $entries = collect([[route('universities.index'), $unis->pluck('updated_at')->filter()->max(), 'All universities']]);
        $unis->each(fn ($u) => $entries->push([route('universities.show', $u->slug), $u->updated_at, $u->name]));

        return $entries;
    }

    private function destinations(): Collection
    {
        $countries = Country::destinations()->where('guidance_published', true)->orderBy('name')->get();
        if ($countries->isEmpty()) {
            return collect();
        }
        $entries = collect([[route('destinations.index'), $countries->map(fn ($c) => $c->guidance_reviewed_at ?? $c->updated_at)->filter()->max(), 'All study destinations']]);
        $countries->each(fn ($c) => $entries->push([route('destinations.show', $c), $c->guidance_reviewed_at ?? $c->updated_at, 'Study in '.$c->name]));

        return $entries;
    }

    private function blog(): Collection
    {
        $posts = BlogPost::published()->get(['slug', 'title', 'updated_at']);
        if ($posts->isEmpty()) {
            return collect();
        }
        $entries = collect([[route('blog.index'), $posts->pluck('updated_at')->filter()->max(), 'The blog']]);
        BlogCategory::query()->whereHas('posts', fn ($p) => $p->published())->get()->each(fn ($c) => $entries->push([route('blog.category', $c->slug), $c->updated_at, $c->name]));
        $posts->each(fn ($b) => $entries->push([route('blog.show', $b->slug), $b->updated_at, $b->title]));

        return $entries;
    }
}
