<?php

namespace App\Http\Controllers;

use App\Domain\Scholarship\DeadlineService;
use App\Models\Article;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\ScholarshipCycle;
use App\Models\Service;
use App\Models\Subject;

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

        return view('public.home', [
            'degrees' => DegreeLevel::orderBy('sort')->get(),
            'destinationsAll' => Country::destinations()->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'featured' => $featured,
            'closingSoon' => $closingSoon,
            'destinations' => Country::destinations()->where('guidance_published', true)->where('featured', true)->limit(4)->get(),
            'services' => Service::published()->where('featured', true)->limit(6)->get(),
            'articles' => Article::published()->limit(3)->get(),
        ]);
    }
}
