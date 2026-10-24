<?php

namespace App\Http\Controllers;

use App\Domain\StudentWorkspace\MatchingService;
use App\Enums\PublicationState;
use App\Models\AnalyticsEvent;
use App\Models\SavedScholarship;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\ScholarshipRoute;
use Illuminate\Support\Facades\DB;

class ScholarshipController extends Controller
{
    public function index()
    {
        return view('public.scholarships.index');
    }

    /** DIR-05. Only published (and, labelled, previously published archived) cycles resolve; drafts are 404. */
    public function show(ScholarshipProgramme $programme, string $cycle, MatchingService $matching)
    {
        $slugOrOld = $cycle;
        $cycle = ScholarshipCycle::where('programme_id', $programme->id)->where('slug', $slugOrOld)
            ->whereIn('publication_state', [PublicationState::Published->value, PublicationState::Archived->value])
            ->whereNotNull('published_at')
            ->with(['programme.funder', 'programme.universities', 'routes.university', 'destinations', 'nationalities', 'excludedNationalities', 'degreeLevels', 'subjects',
                'universities', 'benefits', 'requirements', 'criteria', 'currentDeadlines', 'evidence', 'verifier'])
            ->first();

        if (! $cycle) { // an address that was changed: send people (and search engines) to the new one for good
            $moved = DB::table('cycle_slug_redirects')->where('programme_id', $programme->id)->where('slug', $slugOrOld)->value('cycle_id');
            $current = $moved ? ScholarshipCycle::visible()->where('id', $moved)->value('slug') : null;
            abort_unless($current, 404);

            return redirect()->route('scholarships.show', [$programme->slug, $current], 301);
        }

        AnalyticsEvent::create(['name' => 'scholarship_view', 'cycle_id' => $cycle->id]);

        $newer = $cycle->publication_state === PublicationState::Archived
            ? ScholarshipCycle::visible()->where('programme_id', $programme->id)->where('intake_year', '>', $cycle->intake_year)->orderBy('intake_year')->first()
            : null;
        $related = ScholarshipCycle::visible()->where('id', '!=', $cycle->id)
            ->whereHas('destinations', fn ($q) => $q->whereIn('countries.id', $cycle->destinations->pluck('id')))
            ->with(['programme.funder', 'destinations', 'degreeLevels', 'currentDeadlines', 'universities', 'programme.universities'])->limit(3)->get();

        $user = auth()->user();
        $match = $user ? $matching->evaluate($user->studentProfile, $cycle) : null;

        return view('public.scholarships.show', [
            'cycle' => $cycle, 'programme' => $programme, 'newer' => $newer, 'related' => $related, 'match' => $match,
            'saved' => $user ? SavedScholarship::where(['user_id' => $user->id, 'cycle_id' => $cycle->id])->exists() : false,
            'plan' => $user ? $user->applicationPlans()->where('cycle_id', $cycle->id)->first() : null,
        ]);
    }

    /** CORE-02 / ANA-01: record the official-link click (no personal data) then send the visitor to the official page. */
    public function out(ScholarshipRoute $route)
    {
        $route->load('cycle');
        abort_unless($route->cycle->isPublished() && $route->application_url, 404);
        $scheme = strtolower((string) parse_url($route->application_url, PHP_URL_SCHEME));
        abort_unless(in_array($scheme, ['http', 'https'], true), 404);

        AnalyticsEvent::create(['name' => 'official_link_click', 'cycle_id' => $route->cycle_id, 'meta' => ['domain' => $route->application_domain]]);

        return redirect()->away($route->application_url);
    }
}
