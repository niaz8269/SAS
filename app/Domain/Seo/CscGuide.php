<?php

namespace App\Domain\Seo;

use App\Domain\Scholarship\DeadlineService;
use App\Models\Country;
use App\Models\ScholarshipCycle;
use App\Support\Html;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The Chinese Government Scholarship guide: one page about the published calls that carry that name or are run by the China Scholarship Council (CSC),
 * written only from what SAS stores about them (requirements, funding components, degree levels, nationalities, deadlines, sources). Nothing is
 * assumed about how the scholarship works beyond those stored facts. The page exists only while at least MIN such calls are published.
 */
class CscGuide
{
    public const MIN = 2;

    private const TTL = 600;

    /** Stored document type => how it reads in a sentence. */
    private const DOCUMENT_WORDS = ['reference' => 'reference letter', 'medical' => 'medical examination', 'language' => 'language certificate'];

    private const COVERAGE = ['covered' => 'covered', 'partial' => 'partly covered', 'not_covered' => 'not covered'];

    /** For the shared lists (sitemap, footer, site map): the title, how many calls, which ones and when they last changed; null when there are too few calls. */
    public function summary(): ?array
    {
        $cycles = $this->query()->get(['id', 'updated_at']);
        if ($cycles->count() < self::MIN) {
            return null;
        }

        return ['title' => 'Chinese Government Scholarship: Requirements and Deadlines', 'count' => $cycles->count(), 'ids' => $cycles->pluck('id')->all(),
            'updated' => $cycles->max('updated_at')?->toDateString()];
    }

    /** Everything the page shows, or null when there is no page. */
    public function content(): ?array
    {
        $version = Cache::get('landing.version', 0);

        return Cache::remember('csc.content.'.$version, self::TTL, fn () => $this->build());
    }

    private function query(): Builder
    {
        return ScholarshipCycle::visible()->whereHas('programme', fn ($p) => $p
            ->where('title', 'like', '%Chinese Government Scholarship%')
            ->orWhereHas('funder', fn ($f) => $f->where('name', 'like', '%China Scholarship Council%')));
    }

    private function build(): ?array
    {
        $cycles = $this->query()
            ->with(['programme.funder', 'degreeLevels', 'benefits', 'requirements', 'evidence', 'universities', 'currentDeadlines', 'nationalities', 'excludedNationalities', 'subjects'])
            ->orderBy('id')->get();
        $n = $cycles->count();
        if ($n < self::MIN) {
            return null;
        }
        $facts = app(ScholarshipFacts::class);
        $deadlines = app(DeadlineService::class);

        $calls = $cycles->map(function (ScholarshipCycle $c) use ($facts, $deadlines) {
            $next = $deadlines->nextClosing($c);

            return [
                'title' => $c->programme->title,
                'url' => route('scholarships.show', [$c->programme->slug, $c->slug]),
                'funder' => $c->programme->funder?->name,
                'levels' => $c->degreeLevels->pluck('name')->all(),
                'next' => $next?->format('j F Y'),
                'sort' => $next?->getTimestamp(),
                'who' => $facts->whoCanApply($c),
                'covers' => $c->benefits->where('coverage', 'covered')->map(fn ($b) => mb_strtolower(str_replace('_', ' ', $b->type)))->values()->all(),
                'verified' => (bool) $c->fully_funded_verified,
                'universities' => $c->universities->count(),
                'benefits' => $c->benefits->map(fn ($b) => [
                    'type' => $b->type,
                    'label' => ucfirst(str_replace('_', ' ', $b->type)),
                    'coverage' => $b->coverage,
                    'limit' => trim(($b->limit_text ?? '').' '.($b->conditions ?? '')),
                ])->all(),
                'documents' => $c->requirements->where('mandatory', true)
                    ->map(fn ($r) => rtrim(Str::limit(trim(Html::text((string) $r->text)), 140, '…', true), '.'))->filter()->values()->all(),
                'document_types' => $c->requirements->where('mandatory', true)->pluck('document_type')->unique()->values()->all(),
                'sources' => $c->evidence->map(fn ($e) => ['publisher' => $e->publisher, 'url' => $e->url, 'checked' => $e->checked_at->format('j M Y')])->all(),
            ];
        })->all();

        $names = array_map(fn ($c) => $c['title'], $calls);
        $funders = collect($calls)->pluck('funder')->filter()->unique()->values()->all();
        $levels = $cycles->flatMap(fn ($c) => $c->degreeLevels->pluck('name'))->countBy()->sortDesc();
        $verified = $cycles->pluck('last_verified_at')->filter()->max();

        // Closing dates, soonest first; calls with no confirmed upcoming date are simply not in this list.
        $dated = collect($calls)->filter(fn ($c) => $c['next'] !== null)->sortBy('sort')->values();
        $deadlineText = $dated->isNotEmpty()
            ? 'The next confirmed closing '.($dated->count() === 1 ? 'date is ' : 'dates are ').Landing::listText($dated->map(fn ($c) => $c['title'].' on '.$c['next'])->all()).'. Always check the call page and the official source before you apply.'
            : null;

        // What is covered: what every call covers, and where calls differ.
        $types = $cycles->flatMap(fn ($c) => $c->benefits->pluck('type'))->unique()->values();
        $everyCall = [];
        $differences = [];
        foreach ($types as $type) {
            $label = mb_strtolower(str_replace('_', ' ', $type));
            $coverages = $cycles->map(fn ($c) => $c->benefits->firstWhere('type', $type)?->coverage);
            if ($coverages->every(fn ($v) => $v === 'covered')) {
                $everyCall[] = $label;

                continue;
            }
            foreach ($cycles as $i => $c) {
                $cov = $coverages[$i];
                if (isset(self::COVERAGE[$cov])) {
                    $differences[$label][self::COVERAGE[$cov]][] = $c->programme->title;
                }
            }
        }
        $differenceText = [];
        foreach ($differences as $label => $byCoverage) {
            $parts = [];
            foreach ($byCoverage as $coverage => $titles) {
                $parts[] = $coverage.' in '.Landing::listText($titles);
            }
            $differenceText[] = ucfirst($label).': '.implode('; ', $parts).'.';
        }
        $coverText = $everyCall
            ? 'Stored as covered in every one of these calls: '.Landing::listText($everyCall).'.'
            : 'The funding components differ between calls; the table for each call shows them.';
        $verifiedCount = collect($calls)->where('verified', true)->count();
        $fundingNote = $verifiedCount === 0
            ? 'SAS has not verified full funding for any of these calls, so none is labelled "fully funded". The funding components each call states are listed on this page.'
            : 'SAS has verified full funding for '.$verifiedCount.' of these '.$n.' calls; the others are not labelled "fully funded".';

        // Documents: how many each call lists, and which kinds appear in most of them.
        $counts = collect($calls)->map(fn ($c) => count($c['documents']))->filter(fn ($k) => $k > 0);
        $typeCounts = collect($calls)->flatMap(fn ($c) => $c['document_types'])->countBy()->sortDesc();
        $common = $typeCounts->filter(fn ($k, $type) => $k * 2 >= $n && $type !== 'other')->keys()->map(fn ($t) => self::DOCUMENT_WORDS[$t] ?? mb_strtolower(str_replace('_', ' ', $t)))->take(8)->all();
        $documentText = $counts->isNotEmpty()
            ? 'The calls list between '.$counts->min().' and '.$counts->max().' required documents or conditions each.'.($common ? ' Kinds of document that appear in at least half of the calls: '.Landing::listText($common).'.' : '')
            : null;

        $nationalityAny = $cycles->where('any_nationality', true)->count();
        $whoText = $nationalityAny > 0
            ? $nationalityAny.' of the '.$n.' calls are open to students of any nationality; the others name the nationalities they accept. Eligibility for each call is listed below.'
            : 'These calls name the nationalities they accept. Eligibility for each call is listed below.';

        $opening = 'SAS lists '.$n.' published calls that carry the Chinese Government Scholarship name or are run by the China Scholarship Council (CSC): '.Landing::listText($names).'.';
        $intro = [
            $opening,
            'Degree levels covered: '.Landing::listText($levels->map(fn ($k, $name) => $name.' ('.$k.')')->values()->all()).'.',
        ];
        if ($funders) {
            $intro[] = 'Organisations named as funders: '.Landing::listText($funders).'.';
        }
        if ($verified) {
            $intro[] = 'Each call names its official source and the date it was last checked; the most recent check on this page was on '.$verified->format('j F Y').'.';
        }

        $sources = collect($calls)->flatMap(fn ($c) => $c['sources'])->unique(fn ($s) => $s['url'])->values();
        $sourceText = $sources->isNotEmpty()
            ? 'From '.Landing::listText($sources->map(fn ($s) => $s['publisher'].' (checked '.$s['checked'].')')->unique()->take(5)->values()->all()).'. Each source is linked on the call page.'
            : null;

        $faq = [
            ['Which Chinese Government Scholarship calls does SAS list?', $opening],
            ['Which degree levels do they cover?', Landing::listText($levels->map(fn ($k, $name) => $name.' ('.$k.')')->values()->all()).'.'],
        ];
        if ($documentText) {
            $faq[] = ['What are the requirements and documents?', $documentText.' The exact list, with its conditions, is given for each call on this page.'];
        }
        if ($deadlineText) {
            $faq[] = ['What are the deadlines?', $deadlineText];
        }
        $faq[] = ['What does the scholarship cover?', $coverText.($differenceText ? ' Where calls differ: '.implode(' ', $differenceText) : '')];
        $faq[] = ['Who can apply?', $whoText];
        $faq[] = ['Is the Chinese Government Scholarship fully funded?', $fundingNote];
        if ($sourceText) {
            $faq[] = ['Where does this information come from?', $sourceText];
        }

        $china = Country::where('slug', 'china')->first(['id', 'name', 'slug', 'is_destination', 'guidance_published']);

        return [
            'title' => 'Chinese Government Scholarship: Requirements and Deadlines',
            'description' => Str::limit($opening, 155),
            'count' => $n,
            'intro' => $intro,
            'calls' => $calls,
            'cover' => ['text' => $coverText, 'differences' => $differenceText, 'note' => $fundingNote],
            'document_text' => $documentText,
            'deadline_text' => $deadlineText,
            'dated' => $dated->map(fn ($c) => ['title' => $c['title'], 'url' => $c['url'], 'date' => $c['next']])->all(),
            'who_text' => $whoText,
            'sources' => $sources->map(fn ($s) => ['publisher' => $s['publisher'], 'url' => $s['url'], 'checked' => $s['checked']])->take(8)->all(),
            'faq' => $faq,
            'china_url' => $china && $china->is_destination && $china->guidance_published ? route('destinations.show', $china) : null, // plain text: cached data must not hold models
            'updated' => $cycles->max('updated_at')?->toDateString(),
        ];
    }
}
