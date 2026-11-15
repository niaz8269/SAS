<?php

namespace App\Http\Controllers;

use App\Domain\Catalogue\CscCatalogue;
use App\Domain\StudentWorkspace\MatchingService;
use App\Enums\PublicationState;
use App\Models\AnalyticsEvent;
use App\Models\DegreeLevel;
use App\Models\SavedScholarship;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\ScholarshipRoute;
use App\Support\Crawler;
use Illuminate\Http\Request;
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
                'universities', 'benefits', 'requirements', 'applicationSteps', 'criteria', 'currentDeadlines', 'evidence', 'verifier'])
            ->first();

        if (! $cycle) { // an address that was changed: send people (and search engines) to the new one for good
            $moved = DB::table('cycle_slug_redirects')->where('programme_id', $programme->id)->where('slug', $slugOrOld)->value('cycle_id');
            $current = $moved ? ScholarshipCycle::visible()->where('id', $moved)->value('slug') : null;
            abort_unless($current, 404);

            return redirect()->route('scholarships.show', [$programme->slug, $current], 301);
        }

        // A view is a person reading the page: not a search-engine crawler, not a HEAD check (each of those is also a database write on a shared SQLite file).
        if (Crawler::isHumanView(request())) {
            AnalyticsEvent::create(['name' => 'scholarship_view', 'cycle_id' => $cycle->id]);
        }

        $newer = $cycle->publication_state === PublicationState::Archived
            ? ScholarshipCycle::visible()->where('programme_id', $programme->id)->where('intake_year', '>', $cycle->intake_year)->orderBy('intake_year')->first()
            : null;
        $related = ScholarshipCycle::visible()->where('id', '!=', $cycle->id)
            ->whereHas('destinations', fn ($q) => $q->whereIn('countries.id', $cycle->destinations->pluck('id')))
            ->with(['programme.funder', 'destinations', 'degreeLevels', 'currentDeadlines', 'universities', 'programme.universities', 'benefits'])->limit(12)->get()
            // Same destination first, and among those the ones that share a degree level: six, so a reader and a crawler both have more to follow.
            ->sortByDesc(fn ($r) => $r->degreeLevels->pluck('id')->intersect($cycle->degreeLevels->pluck('id'))->count())->take(6)->values();

        $user = auth()->user();
        $match = $user ? $matching->evaluate($user->studentProfile, $cycle) : null;

        return view('public.scholarships.show', [
            'cycle' => $cycle, 'programme' => $programme, 'newer' => $newer, 'related' => $related, 'match' => $match,
            'saved' => $user ? SavedScholarship::where(['user_id' => $user->id, 'cycle_id' => $cycle->id])->exists() : false,
            'plan' => $user ? $user->applicationPlans()->where('cycle_id', $cycle->id)->first() : null,
        ]);
    }

    /** CORE-02 / ANA-01: record the official-link click (no personal data) then send the visitor to the official page. */
    /** Every programme or major the scholarship names, by degree level, with a word search: students look for their major, not for a scholarship's name. */
    public function majors(Request $request, ScholarshipProgramme $programme, string $cycle)
    {
        $cycle = ScholarshipCycle::where('programme_id', $programme->id)->where('slug', $cycle)
            ->whereIn('publication_state', [PublicationState::Published->value, PublicationState::Archived->value])->whereNotNull('published_at')
            ->with(['programme.funder', 'degreeLevels'])->firstOrFail();

        $data = $request->validate(['q' => 'nullable|string|max:80', 'level' => 'nullable|string|max:32']);
        $q = trim((string) ($data['q'] ?? ''));
        $level = (string) ($data['level'] ?? '');

        $base = $cycle->offerings()->whereNotNull('name')->with('degreeLevel');
        $counts = $cycle->offerings()->whereNotNull('name')->selectRaw('degree_level_id, count(*) as n')->groupBy('degree_level_id')->pluck('n', 'degree_level_id');
        $levels = DegreeLevel::whereIn('id', $counts->keys())->orderBy('id')->get()->mapWithKeys(fn ($l) => [$l->slug => ['name' => $l->name, 'count' => (int) $counts[$l->id]]]);
        $rows = $base->when($level !== '', fn ($w) => $w->whereHas('degreeLevel', fn ($l) => $l->where('slug', $level)))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')->orWhere('institution', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->orderBy('degree_level_id')->orderBy('sort')->orderBy('id')->paginate(100)->withQueryString();

        // The Chinese Government Scholarship calls: their programmes are also every programme that the Study in China system lists as open to CGS applicants.
        $catalogue = null;
        $catalogueTotal = 0;
        if (CscCatalogue::appliesTo($cycle)) {
            $csc = app(CscCatalogue::class);
            $catalogue = $csc->query($q, $level)->with('university:id,name,slug,city,province')->orderByRaw('COALESCE(name_en, name_zh)')->orderBy('id')->paginate(50, ['*'], 'cp')->withQueryString();
            $catalogueTotal = array_sum($csc->counts());
            foreach (CscCatalogue::levelNames() as $slug => $name) {
                if (($csc->counts()[$slug] ?? 0) > 0) {
                    $levels[$slug] = ['name' => $name, 'count' => ($levels[$slug]['count'] ?? 0) + $csc->counts()[$slug]];
                }
            }
        }
        abort_if(($levels->isEmpty() && ! $catalogue) || $rows->currentPage() > max(1, $rows->lastPage()) || ($catalogue && $catalogue->currentPage() > max(1, $catalogue->lastPage())), 404);

        return view('public.scholarships.majors', ['cycle' => $cycle, 'programme' => $programme, 'rows' => $rows, 'levels' => $levels, 'q' => $q, 'level' => $level, 'catalogue' => $catalogue, 'catalogueTotal' => $catalogueTotal]);
    }

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
