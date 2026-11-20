<?php

namespace App\Domain\Seo;

use App\Domain\Scholarship\DeadlineService;
use App\Models\Country;
use App\Models\ScholarshipCycle;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Scholarships for Pakistani students: one hub and its facets (by degree in the words Pakistani students use: MS, PhD, BS; fully funded;
 * by destination). A scholarship is listed when it names Pakistan among the eligible countries, or is open to any nationality and does
 * not exclude Pakistan. Every sentence is built from stored data; a facet page exists only while it has at least MIN scholarships.
 */
class PakistanHub
{
    public const MIN = 3;

    private const TTL = 600;

    /** facet => [degree level code or null, words in the title, short label]. */
    private const DEGREES = [
        'ms' => ['master', 'MS', "MS and master's"],
        'phd' => ['doctorate', 'PhD', 'PhD'],
        'bs' => ['bachelor', 'BS', 'BS and undergraduate'],
    ];

    /** How a destination reads in a title ("in the UK", not "in United Kingdom"). */
    private const SHORT = ['United Kingdom' => 'the UK', 'United States' => 'the USA', 'United Arab Emirates' => 'the UAE', 'Hong Kong SAR China' => 'Hong Kong', 'Netherlands' => 'the Netherlands'];

    private ?Country $pakistan = null;

    public function country(): ?Country
    {
        return $this->pakistan ??= Country::where('code', 'PK')->first();
    }

    /** The intake year people search for: from September the next year's calls are the ones being applied to. */
    public static function year(): int
    {
        return now()->month >= 9 ? now()->year + 1 : now()->year;
    }

    /** Published scholarships a Pakistani student may apply for. */
    public function eligible(): Builder
    {
        $pk = $this->country()?->id ?? 0;

        return ScholarshipCycle::visible()->where(fn (Builder $q) => $q
            ->whereHas('nationalities', fn ($n) => $n->where('countries.id', $pk))
            ->orWhere(fn (Builder $o) => $o->where('any_nationality', true)->whereDoesntHave('excludedNationalities', fn ($n) => $n->where('countries.id', $pk))));
    }

    public function isEligible(ScholarshipCycle $cycle): bool
    {
        $pk = $this->country()?->id;

        return $pk !== null && ($cycle->nationalities->contains('id', $pk) || ($cycle->any_nationality && ! $cycle->excludedNationalities->contains('id', $pk)));
    }

    /**
     * The hub and every facet that has enough scholarships, for the sitemap, the footer and the links between them.
     *
     * @return Collection<int, array{type:string, slug:string, facet:?string, url:string, label:string, title:string, name:string, count:int, ids:list<int>, updated:?string, country:?string, priority?:bool}>
     */
    public function pages(): Collection
    {
        return collect(Cache::remember('pk.pages.'.Cache::get('landing.version', 0), self::TTL, fn () => $this->build()));
    }

    public function find(?string $facet): ?array
    {
        return $this->pages()->first(fn ($p) => $p['facet'] === $facet);
    }

    /** The scholarships of a page, the soonest confirmed closing date first. */
    public function query(array $page): Builder
    {
        return ScholarshipCycle::visible()->whereIn('id', $page['ids'])
            ->with(['programme.funder', 'destinations', 'degreeLevels', 'currentDeadlines', 'universities', 'programme.universities', 'benefits'])
            ->orderByRaw('next_closing_sort IS NULL')->orderBy('next_closing_sort')->orderBy('id');
    }

    private function build(): array
    {
        if (! $this->country()) {
            return [];
        }
        $year = self::year();
        $cycles = $this->eligible()->with(['destinations:id,name,slug', 'degreeLevels:id,code'])->get(['id', 'funding_type', 'fully_funded_verified', 'updated_at']);
        $pages = [];
        $add = function (?string $facet, string $label, string $title, string $name, Collection $set, ?string $country = null) use (&$pages) {
            if ($set->count() < self::MIN) {
                return;
            }
            $pages[] = ['type' => 'pk', 'slug' => 'pakistan'.($facet ? '-'.$facet : ''), 'facet' => $facet,
                'url' => $facet ? route('pakistan.facet', $facet) : route('pakistan.hub'), 'label' => $label, 'title' => $title, 'name' => $name,
                'count' => $set->count(), 'ids' => $set->pluck('id')->all(), 'updated' => $set->max('updated_at')?->toDateString(), 'country' => $country, 'priority' => $facet === null];
        };

        $add(null, 'Scholarships for Pakistani students', 'Scholarships for Pakistani Students '.$year, 'scholarships for Pakistani students', $cycles);
        $add('fully-funded', 'Fully funded for Pakistani students', 'Fully Funded Scholarships for Pakistani Students '.$year, 'fully funded scholarships for Pakistani students',
            $cycles->filter(fn ($c) => $c->funding_type === 'full' && $c->fully_funded_verified));
        foreach (self::DEGREES as $facet => [$code, $words, $label]) {
            $add($facet, $label.' scholarships for Pakistani students', $words.' Scholarships for Pakistani Students '.$year, mb_strtolower($label) === 'phd' ? 'PhD scholarships for Pakistani students' : $label.' scholarships for Pakistani students',
                $cycles->filter(fn ($c) => $c->degreeLevels->contains('code', $code)));
        }
        foreach ($cycles->flatMap(fn ($c) => $c->destinations)->unique('id')->sortBy('name') as $country) {
            if ($country->slug === 'pakistan') {
                continue; // studying in Pakistan is not what "scholarships for Pakistani students" means
            }
            $where = self::SHORT[$country->name] ?? $country->name;
            $add('in-'.$country->slug, 'Pakistani students in '.$where, 'Scholarships for Pakistani Students in '.$where.' '.$year, 'scholarships for Pakistani students in '.$where,
                $cycles->filter(fn ($c) => $c->destinations->contains('id', $country->id)), $country->name);
        }

        return $pages;
    }

    /** @return array{paragraphs: list<string>, faq: list<array{0:string,1:string}>, soonest: list<array{name:string,date:string,url:string}>, checklist: list<string>, services: list<array{0:string,1:string}>} */
    public function content(array $page): array
    {
        return Cache::remember('pk.content.'.Cache::get('landing.version', 0).'.'.($page['facet'] ?? 'hub'), self::TTL, fn () => $this->write($page));
    }

    private function write(array $page): array
    {
        $pk = $this->country();
        $cycles = $this->query($page)->with(['nationalities:id', 'programme'])->get();
        $n = $cycles->count();
        $named = $cycles->filter(fn ($c) => $c->nationalities->contains('id', $pk->id))->count();
        $open = $n - $named;
        $year = self::year();
        $deadlines = app(DeadlineService::class);

        $paragraphs = [];
        $paragraphs[] = 'SAS lists '.$n.' published '.$page['name'].' for '.$year.'. '
            .($named ? $named.' of them name Pakistan among the eligible countries' : 'None of them names Pakistan specifically')
            .($open ? ($named ? ' and ' : '; ').$open.' '.($open === 1 ? 'is' : 'are').' open to students of any nationality' : '')
            .'. Each listing shows the deadline, what the funding covers, the documents asked for and the official source it was checked against.';

        $ff = $cycles->filter(fn ($c) => $c->funding_type === 'full' && $c->fully_funded_verified);
        if ($page['facet'] !== 'fully-funded') {
            $paragraphs[] = $ff->isNotEmpty()
                ? $ff->count().' of these '.($ff->count() === 1 ? 'is' : 'are').' verified as fully funded: tuition and a living allowance are covered, as the funder states.'
                : 'None of these is verified as fully funded yet; the funding line on each listing says exactly what is covered.';
        }
        if (! str_starts_with((string) $page['facet'], 'in-')) {
            $top = $cycles->flatMap(fn ($c) => $c->destinations->pluck('name'))->reject(fn ($d) => $d === 'Pakistan')->countBy()->sortDesc()->take(4);
            if ($top->isNotEmpty()) {
                $paragraphs[] = 'The most offered destinations are '.$this->join($top->map(fn ($k, $d) => (self::SHORT[$d] ?? $d).' ('.$k.')')->values()->all()).'.';
            }
        }

        $soon = $cycles->filter(fn ($c) => $c->next_closing_sort && $c->next_closing_sort->isFuture())->sortBy('next_closing_sort')->take(3)->values();
        $soonest = $soon->map(fn ($c) => ['name' => $c->programme->title, 'date' => $c->next_closing_sort->format('j F Y'), 'url' => route('scholarships.show', [$c->programme->slug, $c->slug])])->all();
        if ($soon->isNotEmpty()) {
            $paragraphs[] = 'The next confirmed deadline is '.$soon[0]->programme->title.', on '.$soon[0]->next_closing_sort->format('j F Y').'.';
        }

        $faq = [];
        $faq[] = ['Which scholarships can Pakistani students apply for in '.$year.'?', $paragraphs[0]];
        $faq[] = ['Are there fully funded scholarships for Pakistani students?', $ff->isNotEmpty()
            ? 'Yes. '.$ff->count().' of the '.$page['name'].' listed here '.($ff->count() === 1 ? 'is' : 'are').' verified as fully funded, for example '.$this->join($ff->take(4)->map(fn ($c) => $c->programme->title)->all()).'.'
            : 'None of these listings is verified as fully funded yet. Each one says exactly which costs are covered.'];
        $faq[] = ['Which of these scholarships close next?', $soon->isNotEmpty()
            ? 'The next confirmed deadlines are '.$this->join($soon->map(fn ($c) => $c->programme->title.' ('.$c->next_closing_sort->format('j F Y').')')->all()).'. Deadlines are checked against the funder and can change, so confirm on the listing before you apply.'
            : 'None of these has a confirmed upcoming deadline at the moment. Dates are added as soon as the funder confirms them; each listing shows the last known timeline.'];
        if ($page['facet'] === null) {
            $big = $cycles->filter(fn ($c) => preg_match('/Chevening|Commonwealth|Fulbright|Chinese Government|Stipendium Hungaricum|Turkiye|Türkiye|Global Korea|MEXT|DAAD|Erasmus/i', $c->programme->title));
            if ($big->isNotEmpty()) {
                $faq[] = ['Can Pakistani students apply for Chevening, Commonwealth, Fulbright, the Chinese Government Scholarship and similar awards?',
                    'Pakistani students are eligible for these well-known awards listed on SAS: '.$this->join($big->take(8)->map(fn ($c) => $c->programme->title)->unique()->values()->all()).'. Open each listing for the eligibility rules, documents and dates.'];
            }
        }
        $faq[] = ['Does it cost anything to use these listings?', 'No. Reading the scholarships, deadlines and requirements on SAS is free and needs no account. SAS charges only for services you choose, such as help with document attestation or a review of your application.'];

        $checklist = [
            'A valid passport. Many funders ask for it when you apply, not only for the visa.',
            'Degree certificates and transcripts. For degrees from a Pakistani university, attestation by the Higher Education Commission (HEC) is often asked for.',
            'Matric and intermediate (SSC and HSSC) certificates, with attestation or an equivalence certificate from the Inter Board Committee of Chairmen (IBCC) where it is asked for.',
            'An English test result (IELTS, TOEFL, PTE or Duolingo), or a letter that your degree was taught in English where a scholarship accepts one. Requirements differ, so check each listing.',
            'A statement of purpose or study plan, reference letters and a CV, as the listing asks.',
            'Translations, and attestation by the Ministry of Foreign Affairs (MOFA), when an embassy or university asks for them.',
        ];
        $services = Service::whereIn('slug', ['attestation-support', 'translation', 'application-review', 'scholarship-advisory'])->where('is_available', true)->get(['name', 'slug'])
            ->map(fn ($s) => [$s->name, route('services.show', $s->slug)])->all();

        return ['paragraphs' => $paragraphs, 'faq' => $faq, 'soonest' => $soonest, 'checklist' => $checklist, 'services' => $services];
    }

    private function join(array $items): string
    {
        $items = array_values($items);

        return count($items) <= 1 ? (string) ($items[0] ?? '') : implode(', ', array_slice($items, 0, -1)).' and '.end($items);
    }
}
