<?php

namespace App\Domain\Seo;

use App\Domain\Scholarship\DeadlineService;
use App\Models\Country;
use App\Models\ScholarshipCycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Landing pages that answer a search people really make ("master's scholarships for international students", "scholarships in China",
 * "fully funded scholarships"), made only from the published scholarships. A page exists only while it has at least MIN of them, so there is never a
 * thin page; the page, its sitemap entry and its footer link all come from the same list, so they appear and go with the data.
 * Every sentence written here is a count, a name or a date that is stored. A fact that is not stored is not mentioned.
 */
class Landing
{
    public const MIN = 3;

    /** URL word => [degree level code, link text, name used in sentences, page title]. */
    private const DEGREES = [
        'bachelor' => ['bachelor', "Bachelor's scholarships", "bachelor's scholarships", "Bachelor's Scholarships for International Students"],
        'masters' => ['master', "Master's scholarships", "master's scholarships", "Master's Scholarships for International Students"],
        'phd' => ['doctorate', 'PhD scholarships', 'PhD and doctorate scholarships', 'PhD and Doctorate Scholarships for International Students'],
        'postdoctoral' => ['postdoctoral', 'Postdoctoral scholarships', 'postdoctoral scholarships', 'Postdoctoral Scholarships for International Students'],
        'diploma' => ['diploma', 'Diploma and certificate scholarships', 'diploma and certificate scholarships', 'Diploma and Certificate Scholarships for International Students'],
    ];

    /** URL word => [funding_type, name used in sentences, page title, needs the verified "fully funded" flag]. */
    private const FUNDING = [
        'fully-funded' => ['full', 'fully funded', 'Fully Funded Scholarships for International Students', true],
        'partial-funding' => ['partial', 'partially funded', 'Partially Funded Scholarships for International Students', false],
        'tuition-covered' => ['tuition_only', 'tuition-covered', 'Scholarships That Cover Tuition for International Students', false],
    ];

    /** Students' home countries people search with ("scholarships for Nigerian students"); a page exists for each one that has enough scholarships naming it. */
    private const NATIONALITIES = ['india', 'pakistan', 'nigeria', 'bangladesh', 'kenya', 'philippines', 'ghana', 'nepal', 'sri-lanka', 'egypt', 'ethiopia', 'uganda', 'tanzania', 'indonesia', 'vietnam', 'zimbabwe'];

    /** Subject slug => the words used for it in a title, where the stored name reads oddly (people search "medical scholarships"). */
    private const SUBJECT_NAMES = ['medicine-and-health' => 'Medical and Health'];

    /** Countries whose name takes "the" in a sentence. */
    private const THE = ['United Kingdom', 'United States', 'United Arab Emirates', 'Netherlands', 'Philippines', 'Czech Republic', 'Maldives', 'Seychelles', 'Bahamas', 'Gambia'];

    private const TTL = 600;

    /**
     * Every landing page that has enough scholarships now.
     *
     * @return Collection<int, array{type:string, slug:string, url:string, label:string, title:string, name:string, count:int, ids:list<int>, updated:?string, country:?string}>
     */
    public function pages(): Collection
    {
        return collect(Cache::remember('landing.pages', self::TTL, fn () => $this->build()));
    }

    public function find(string $type, string $slug): ?array
    {
        return $this->pages()->first(fn ($p) => $p['type'] === $type && $p['slug'] === $slug);
    }

    /** The scholarships of a page, newest closing date first among the open ones. */
    public function query(array $page): Builder
    {
        return ScholarshipCycle::visible()->whereIn('id', $page['ids'])
            ->with(['programme.funder', 'destinations', 'degreeLevels', 'currentDeadlines', 'universities', 'programme.universities'])
            ->orderByRaw('next_closing_sort IS NULL')->orderBy('next_closing_sort')->orderBy('id');
    }

    /** Other landing pages to link to from this one: the other kinds first, then more of the same kind. */
    public function neighbours(array $page, int $limit = 10): Collection
    {
        $others = $this->pages()->reject(fn ($p) => $p['type'] === $page['type'] && $p['slug'] === $page['slug']);

        return $others->sortBy(fn ($p) => [$p['type'] === $page['type'] ? 1 : 0, -$p['count']])->values()->take($limit);
    }

    /** The landing page for a destination country, if it has one. */
    public function forCountry(string $slug): ?array
    {
        return $this->find('in', $slug);
    }

    /** @return array{paragraphs: list<string>, faq: list<array{0:string,1:string}>, soonest: list<array{name:string,date:string,url:string}>} */
    public function content(array $page): array
    {
        $version = Cache::get('landing.version', 0);

        return Cache::remember('landing.content.'.$version.'.'.$page['type'].'.'.$page['slug'], self::TTL, fn () => $this->write($page));
    }

    private function build(): array
    {
        $cycles = ScholarshipCycle::visible()->with(['destinations:id,name,slug', 'degreeLevels:id,code', 'subjects:id,name,slug', 'nationalities:id'])
            ->get(['id', 'funding_type', 'fully_funded_verified', 'any_nationality', 'updated_at']);
        $pages = [];
        $add = function (string $type, string $slug, string $url, string $label, string $title, string $name, Collection $set, ?string $country = null) use (&$pages) {
            if ($set->count() < self::MIN) {
                return;
            }
            $pages[] = ['type' => $type, 'slug' => $slug, 'url' => $url, 'label' => $label, 'title' => $title, 'name' => $name, 'count' => $set->count(),
                'ids' => $set->pluck('id')->all(), 'updated' => $set->max('updated_at')?->toDateString(), 'country' => $country];
        };

        $countries = $cycles->flatMap(fn ($c) => $c->destinations)->unique('id');
        foreach ($countries as $country) {
            $set = $cycles->filter(fn ($c) => $c->destinations->contains('id', $country->id));
            $the = in_array($country->name, self::THE, true) ? 'the ' : '';
            $short = 'Scholarships in '.$the.$country->name;
            $title = $short.' for International Students';
            $add('in', $country->slug, route('landing.destination', $country->slug), $short, mb_strlen($title) <= 60 ? $title : $short, 'scholarships in '.$the.$country->name, $set, $country->name);
        }
        foreach (self::DEGREES as $word => [$code, $label, $noun, $title]) {
            $set = $cycles->filter(fn ($c) => $c->degreeLevels->contains('code', $code));
            $add('for', $word, route('landing.degree', $word), $label, $title, $noun, $set);
        }
        foreach (self::FUNDING as $word => [$type, $name, $title, $verified]) {
            $set = $cycles->filter(fn ($c) => $c->funding_type === $type && (! $verified || $c->fully_funded_verified));
            $add('funding', $word, route('landing.funding', $word), Str::ucfirst($name).' scholarships', $title, $name.' scholarships', $set);
        }
        foreach ($cycles->flatMap(fn ($c) => $c->subjects)->unique('id') as $subject) {
            $set = $cycles->filter(fn ($c) => $c->subjects->contains('id', $subject->id));
            $name = self::SUBJECT_NAMES[$subject->slug] ?? $subject->name;
            $title = $name.' Scholarships for International Students';
            $add('subject', $subject->slug, route('landing.subject', $subject->slug), $name.' scholarships', mb_strlen($title) <= 60 ? $title : $name.' Scholarships', mb_strtolower($name).' scholarships', $set);
        }
        // Only the scholarships that NAME the country are listed; the many open to any nationality have one shared page, so country pages are never copies of each other.
        foreach (Country::whereIn('slug', self::NATIONALITIES)->get(['id', 'name', 'slug', 'demonym']) as $country) {
            $set = $cycles->filter(fn ($c) => $c->nationalities->contains('id', $country->id));
            $who = $country->demonym ? $country->demonym.' students' : 'students from '.$country->name;
            $add('from', $country->slug, route('landing.from', $country->slug), 'Scholarships for '.$who, 'Scholarships for '.Str::replaceLast('students', 'Students', $who), 'scholarships for '.$who, $set, $country->name);
        }
        $add('open', 'any-nationality', route('landing.open'), 'Scholarships open to any nationality', 'Scholarships Open to Students of Any Nationality', 'scholarships open to any nationality', $cycles->where('any_nationality', true));

        // The Chinese Government Scholarship guide has its own layout (see CscGuide) but joins the same lists: sitemap, site map and footer.
        $csc = app(CscGuide::class)->summary();
        if ($csc !== null) {
            $pages[] = ['type' => 'guide', 'slug' => 'chinese-government-scholarship', 'url' => route('csc.guide'), 'label' => 'Chinese Government Scholarship', 'title' => $csc['title'],
                'name' => 'Chinese Government Scholarship calls', 'count' => $csc['count'], 'ids' => $csc['ids'], 'updated' => $csc['updated'], 'country' => null, 'priority' => true];
        }

        return $pages;
    }

    private function write(array $page): array
    {
        $deadlines = app(DeadlineService::class);
        $cycles = $this->query($page)->get();
        $n = $cycles->count();
        $paragraphs = [];

        // Opening: how many, and what they are open to.
        $any = $cycles->where('any_nationality', true)->count();
        $opening = 'SAS lists '.$n.' published '.$page['name'].'.';
        if ($page['type'] === 'from') {
            // These scholarships name the country; those open to any nationality (unless this country is excluded) are on their own page.
            $anyOpen = ScholarshipCycle::visible()->where('any_nationality', true)
                ->whereDoesntHave('excludedNationalities', fn ($q) => $q->where('countries.slug', $page['slug']))->count();
            $opening = 'SAS lists '.$n.' published scholarships that name '.self::withThe($page['country']).' among the nationalities they accept.'
                .($anyOpen > 0 ? ' '.$anyOpen.' more are open to students of any nationality.' : '');
        } elseif ($page['type'] === 'open') {
            $opening = 'SAS lists '.$n.' published scholarships that are open to students of any nationality, unless a scholarship page says that a country is excluded.';
        } elseif ($any > 0) {
            $opening .= ' '.$any.' of them '.($any === 1 ? 'is' : 'are').' open to students of any nationality; the others name the nationalities they accept.';
        }
        $paragraphs[] = $opening;

        // Degree levels and funding, as counts.
        $levels = $cycles->flatMap(fn ($c) => $c->degreeLevels->pluck('name'))->countBy()->sortDesc();
        if ($levels->isNotEmpty() && $page['type'] !== 'for') {
            $paragraphs[] = 'Degree levels covered: '.$levels->map(fn ($k, $name) => $name.' ('.$k.')')->values()->pipe(fn ($c) => $this->join($c->all())).'.';
        }
        $funding = $cycles->map(fn ($c) => $c->fundingLabel())->reject(fn ($l) => $l === 'Funding details not confirmed')->countBy()->sortDesc();
        if ($funding->isNotEmpty() && $page['type'] !== 'funding') {
            $paragraphs[] = 'Funding, where it is confirmed: '.$funding->map(fn ($k, $label) => mb_strtolower($label).' ('.$k.')')->values()->pipe(fn ($c) => $this->join($c->all())).'.';
        }

        // Where they are, for pages that are not about one country.
        if ($page['type'] !== 'in') {
            $where = $cycles->flatMap(fn ($c) => $c->destinations)->countBy('name')->sortDesc()->take(6);
            if ($where->isNotEmpty()) {
                $paragraphs[] = 'Destinations with the most of them: '.$where->map(fn ($k, $name) => $name.' ('.$k.')')->values()->pipe(fn ($c) => $this->join($c->all())).'.';
            }
        }

        // Closing dates that are confirmed (date only: a closing time is shown only where the source gives an exact one).
        $soonest = [];
        foreach ($cycles as $c) {
            $next = $deadlines->nextClosing($c);
            if ($next !== null) {
                $soonest[] = ['name' => $c->programme->title, 'date' => $next->format('j F Y'), 'sort' => $next->getTimestamp(), 'url' => route('scholarships.show', [$c->programme->slug, $c->slug])];
            }
        }
        usort($soonest, fn ($a, $b) => $a['sort'] <=> $b['sort']);
        $soonest = array_map(fn ($s) => ['name' => $s['name'], 'date' => $s['date'], 'url' => $s['url']], array_slice($soonest, 0, 3));
        if ($soonest) {
            $paragraphs[] = 'Next confirmed closing '.(count($soonest) === 1 ? 'date' : 'dates').': '.$this->join(array_map(fn ($s) => $s['name'].' on '.$s['date'], $soonest)).'.';
        }

        $years = $cycles->pluck('intake_year')->filter()->unique()->sort()->values();
        if ($years->isNotEmpty()) {
            $paragraphs[] = 'Intakes listed: '.$this->join($years->all()).'.';
        }
        $verified = $cycles->pluck('last_verified_at')->filter()->max();
        if ($verified) {
            $paragraphs[] = 'Each scholarship names its official source and the date it was last checked; the most recent check on this page was on '.$verified->format('j F Y').'.';
        }

        // Questions a reader asks, answered from the same figures (shown on the page and in the FAQ markup, so both say the same).
        $faq = [[sprintf('How many %s does SAS list?', $page['name']), $opening]];
        if ($soonest) {
            $faq[] = [sprintf('Which %s close soonest?', $page['name']), 'The next confirmed closing '.(count($soonest) === 1 ? 'date is ' : 'dates are ').$this->join(array_map(fn ($s) => $s['name'].' on '.$s['date'], $soonest)).'. Always check the scholarship page and the official source before you apply.'];
        }
        if ($levels->isNotEmpty()) {
            $faq[] = ['Which degree levels do they cover?', $levels->map(fn ($k, $name) => $name.' ('.$k.')')->values()->pipe(fn ($c) => $this->join($c->all())).'.'];
        }
        $full = $cycles->filter(fn ($c) => $c->funding_type === 'full' && $c->fully_funded_verified)->take(5);
        if ($full->isNotEmpty() && $page['slug'] !== 'fully-funded') {
            $faq[] = ['Which of these are fully funded?', 'Verified as fully funded: '.$this->join($full->map(fn ($c) => $c->programme->title)->all()).($cycles->filter(fn ($c) => $c->funding_type === 'full' && $c->fully_funded_verified)->count() > 5 ? ' and others' : '').'.'];
        }
        $faq[] = ['Where can I check the official requirements?', 'Every scholarship page lists the documents it asks for, its eligibility, and a link to the official source with the date SAS last checked it.'];

        return ['paragraphs' => $paragraphs, 'faq' => $faq, 'soonest' => $soonest];
    }

    /** A name as it reads in a sentence: "the United Kingdom", "China". */
    public static function withThe(string $name): string
    {
        return (in_array($name, self::THE, true) ? 'the ' : '').$name;
    }

    /** "a, b and c"; when an item already contains "and" or a comma, the items are separated by semicolons so the list cannot be misread. */
    public static function listText(array $items): string
    {
        $items = array_values($items);
        if (count($items) < 2) {
            return (string) ($items[0] ?? '');
        }
        if (collect($items)->contains(fn ($i) => str_contains((string) $i, ' and ') || str_contains((string) $i, ','))) {
            return implode('; ', $items);
        }
        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }

    private function join(array $items): string
    {
        return self::listText($items);
    }
}
