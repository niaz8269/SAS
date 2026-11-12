<?php

namespace App\Domain\Seo;

use App\Domain\Scholarship\DeadlineService;
use App\Models\ScholarshipCycle;
use Illuminate\Support\Str;

/**
 * Short, plain-language statements about one scholarship, written only from what is stored about it (funder, destinations, degree levels,
 * funding label, nationalities, subjects, the next confirmed closing date, required documents, sources and the date it was last checked).
 * A statement is left out when its data is missing or not confirmed: nothing here is estimated, and nothing says "not confirmed" as filler.
 * Used for the "At a glance" paragraph, the visible "Quick answers" and the matching FAQ markup, so the page and the markup say the same.
 */
class ScholarshipFacts
{
    /** The relations the statements read: all of them are already loaded by ScholarshipController::show. */
    public function glance(ScholarshipCycle $c): string
    {
        $funding = match ($c->fundingLabel()) {
            'Fully funded' => 'fully funded ', 'Partial funding' => 'partially funded ', 'Tuition covered' => 'tuition-covered ', 'Stipend only' => 'stipend-only ', default => '',
        };
        $first = $c->programme->title.' is a '.$funding.'scholarship';
        if ($c->programme->funder?->name) {
            $first .= ' offered by '.$c->programme->funder->name;
        }
        $destinations = $c->destinations->pluck('name');
        if ($destinations->isNotEmpty()) {
            $first .= ' for study in '.$this->join($destinations->take(5)->map(fn ($n) => Landing::withThe($n))->all()).($destinations->count() > 5 ? ' and '.($destinations->count() - 5).' more' : '');
        }
        if ($c->degreeLevels->isNotEmpty()) {
            $first .= ' at '.$this->join($c->degreeLevels->pluck('name')->all()).' level';
        }
        $parts = [$first.'.'];

        if ($nationality = $this->nationalities($c)) {
            $parts[] = $nationality;
        }
        if ($next = $this->nextClosing($c)) {
            $parts[] = 'The next confirmed closing date is '.$next.'.';
        }
        if ($c->last_verified_at) {
            $parts[] = 'SAS last checked this call against the official source on '.$c->last_verified_at->format('j F Y').'.';
        }

        return implode(' ', $parts);
    }

    /** @return list<array{0:string,1:string}> question and answer pairs, only where the answer is stored */
    public function faq(ScholarshipCycle $c): array
    {
        $title = $c->programme->title;
        $faq = [];

        if ($next = $this->nextClosing($c)) {
            $faq[] = ['What is the deadline for '.$title.'?', 'The next confirmed closing date is '.$next.'. Check the timeline on this page and the official source before you apply.'];
        } elseif ($c->effectiveStatus()->value === 'closed') {
            $faq[] = ['Is '.$title.' still open?', 'Applications for this call are closed. See the timeline on this page, and the related scholarships below.'];
        }

        $who = array_filter([
            $this->nationalities($c),
            $c->degreeLevels->isNotEmpty() ? 'Degree levels: '.$this->join($c->degreeLevels->pluck('name')->all()).'.' : null,
            $c->any_subject ? 'Open to any subject.' : ($c->subjects->isNotEmpty() ? 'Subjects: '.$this->join($c->subjects->take(8)->pluck('name')->all()).'.' : null),
            filled($c->teaching_language) ? 'Teaching language: '.$c->teaching_language.'.' : null,
        ]);
        if ($who) {
            $faq[] = ['Who can apply for '.$title.'?', implode(' ', $who)];
        }

        $docs = $c->requirements->where('mandatory', true)->pluck('text')->map(fn ($t) => rtrim(Str::limit(trim(strip_tags((string) $t)), 100, '…', true), '.'))->filter()->values();
        if ($docs->isNotEmpty()) {
            $faq[] = ['What documents are required for '.$title.'?', 'Required documents listed for this call: '.implode('; ', $docs->take(6)->all()).($docs->count() > 6 ? '; and '.($docs->count() - 6).' more' : '').'. The full list, with any conditions, is on this page.'];
        }

        $funding = match (true) {
            $c->funding_type === 'full' && $c->fully_funded_verified => 'Yes. SAS has verified full funding for this call. The funding components and their limits are listed on this page.',
            $c->funding_type === 'full' => 'The source describes this call as fully funded, but SAS has not verified the full list of covered costs, so it is not labelled "fully funded". The components that are known are listed on this page.',
            $c->funding_type === 'partial' => 'No. This call is partially funded. The covered components and their limits are listed on this page.',
            $c->funding_type === 'tuition_only' => 'No. This call covers tuition. The other components are listed on this page.',
            $c->funding_type === 'stipend_only' => 'No. This call provides a stipend. The components are listed on this page.',
            default => null,
        };
        if ($funding) {
            $faq[] = ['Is '.$title.' fully funded?', $funding];
        }

        $sources = $c->evidence->map(fn ($e) => $e->publisher.' (checked '.$e->checked_at->format('j M Y').')')->unique()->take(4)->values();
        if ($sources->isNotEmpty()) {
            $faq[] = ['Where does the information about '.$title.' come from?', 'From '.$this->join($sources->all()).'. Each source is linked at the bottom of this page.'];
        }

        return $faq;
    }

    /** A description for search results when the scholarship has no summary of its own. */
    public function description(ScholarshipCycle $c, int $limit = 155): string
    {
        return Str::limit($this->glance($c), $limit);
    }

    private function nationalities(ScholarshipCycle $c): ?string
    {
        if ($c->any_nationality) {
            $except = $c->excludedNationalities->pluck('name');

            return 'Open to students of any nationality'.($except->isNotEmpty() ? ' except '.$this->join($except->take(8)->all()) : '').'.';
        }
        $nationalities = $c->nationalities->pluck('name');

        return $nationalities->isNotEmpty()
            ? 'Open to students from '.$this->join($nationalities->take(8)->all()).($nationalities->count() > 8 ? ' and '.($nationalities->count() - 8).' more countries' : '').'.'
            : null;
    }

    private function nextClosing(ScholarshipCycle $c): ?string
    {
        // Date only: a closing time is shown elsewhere, and only where the source gives an exact one.
        return app(DeadlineService::class)->nextClosing($c)?->format('j F Y');
    }

    private function join(array $items): string
    {
        return Landing::listText($items);
    }
}
