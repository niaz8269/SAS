<?php

namespace App\Http\Controllers;

use App\Domain\Scholarship\DeadlineService;
use App\Models\Article;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\HomeSection;
use App\Models\ScholarshipCycle;
use App\Models\Service;
use App\Models\Subject;
use App\Models\Testimonial;
use App\Models\TrustItem;
use App\Models\University;
use App\Models\UniversityProgramme;

class HomeController extends Controller
{
    public function __invoke(DeadlineService $deadlines)
    {
        $with = ['programme.funder', 'destinations', 'degreeLevels', 'currentDeadlines', 'universities', 'programme.universities'];
        $now = now();

        // PUB-02: placements are managed by staff flags. Promoted items must not be overdue for recheck (DATA-04).
        $featured = ScholarshipCycle::visible()->where('featured', true)->with($with)
            ->where(fn ($q) => $q->whereNull('review_due_at')->orWhere('review_due_at', '>', $now))
            ->where('application_status', '!=', 'closed')
            ->where(fn ($q) => $q->whereNull('next_closing_sort')->orWhere('next_closing_sort', '>', $now))
            ->orderByRaw('next_closing_sort IS NULL')->orderBy('next_closing_sort')->limit(3)->get();

        // Verified closing dates only: confirmed, still-future, status open, not overdue for recheck.
        $closingSoon = ScholarshipCycle::visible()->with($with)
            ->where('application_status', 'open')
            ->whereNotNull('next_closing_sort')
            ->whereBetween('next_closing_sort', [$now, $now->copy()->addDays(config('sas.deadlines.closing_soon_days'))])
            ->where(fn ($q) => $q->whereNull('review_due_at')->orWhere('review_due_at', '>', $now))
            ->orderBy('next_closing_sort')->limit(3)->get()
            ->filter(fn ($c) => $deadlines->isClosingSoon($c));

        // Nothing flagged as featured yet: show the most recently verified calls instead of an empty box. The same rules apply (not overdue, not closed, not past its deadline).
        $featuredIsFallback = $featured->isEmpty();
        if ($featuredIsFallback) {
            $featured = ScholarshipCycle::visible()->whereNotNull('last_verified_at')->with($with)
                ->where(fn ($q) => $q->whereNull('review_due_at')->orWhere('review_due_at', '>', $now))
                ->where('application_status', '!=', 'closed')
                ->where(fn ($q) => $q->whereNull('next_closing_sort')->orWhere('next_closing_sort', '>', $now))
                ->orderByDesc('last_verified_at')->limit(3)->get();
        }

        // Nothing closes inside the window: show the next confirmed deadlines after it, so the section says something true and useful.
        $nextDeadlines = collect();
        if ($closingSoon->isEmpty()) {
            $nextDeadlines = ScholarshipCycle::visible()->with($with)
                ->where('application_status', 'open')
                ->whereNotNull('next_closing_sort')->where('next_closing_sort', '>', $now->copy()->addDays(config('sas.deadlines.closing_soon_days')))
                ->where(fn ($q) => $q->whereNull('review_due_at')->orWhere('review_due_at', '>', $now))
                ->orderBy('next_closing_sort')->limit(3)->get();
        }

        // Real numbers from the site itself: nothing here is typed in by hand.
        $stats = [
            ['value' => ScholarshipCycle::visible()->count(), 'label' => 'verified scholarship calls', 'url' => route('scholarships.index')],
            ['value' => Country::destinations()->count(), 'label' => 'study destinations', 'url' => route('destinations.index')],
            ['value' => University::inCatalogue()->count(), 'label' => 'universities', 'url' => route('universities.index')],
            ['value' => UniversityProgramme::public()->count(), 'label' => 'degree programmes to search', 'url' => route('programmes.index')],
        ];
        // Universities with their own campus photo first, then the ones with the most programmes.
        $universities = University::inCatalogue()->withCount(['programmes' => fn ($q) => $q->where('published', true)])
            ->orderByDesc('programmes_count')->limit(40)->get()
            ->sortByDesc(fn ($u) => (int) file_exists(public_path('images/universities/'.$u->slug.'.jpg')))->take(8)->values();

        return view('public.home', [
            'featuredIsFallback' => $featuredIsFallback, 'nextDeadlines' => $nextDeadlines, 'stats' => $stats, 'universities' => $universities,
            'sections' => HomeSection::ordered(),
            'trustItems' => TrustItem::where('enabled', true)->orderBy('sort')->orderBy('id')->get(),
            'testimonials' => Testimonial::visible()->orderBy('sort')->orderBy('id')->limit(6)->get(),
            'degrees' => DegreeLevel::orderBy('sort')->get(),
            'destinationsAll' => Country::destinations()->get(),
            'nationalities' => Country::orderBy('name')->get(['name', 'slug']),
            'subjects' => Subject::orderBy('name')->get(),
            'featured' => $featured,
            'closingSoon' => $closingSoon,
            'destinations' => Country::destinations()->where('guidance_published', true)->where('featured', true)->limit(4)->get(),
            'services' => Service::published()->where('featured', true)->limit(6)->get(),
            'articles' => Article::published()->limit(3)->get(),
        ]);
    }
}
