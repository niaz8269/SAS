<?php

namespace App\Http\Controllers\Student;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Documents\Requirements;
use App\Domain\Scholarship\DeadlineService;
use App\Enums\PlanStatus;
use App\Enums\ScanStatus;
use App\Http\Controllers\Controller;
use App\Models\DocumentVersion;
use App\Models\ServiceCase;
use App\Models\StudentProfile;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DeadlineService $deadlines, ApplicationFormService $forms, Requirements $requirements)
    {
        $user = $request->user();
        $profile = $user->studentProfile;

        $plans = $user->applicationPlans()->with(['cycle.programme', 'cycle.degreeLevels', 'cycle.currentDeadlines', 'tasks'])->get();

        // Getting started: the form once, then the scholarships chosen, then each scholarship's documents.
        $formPercent = $forms->percent($forms->formFor($user));
        $docs = $plans->mapWithKeys(fn ($p) => [$p->id => $requirements->progress($requirements->forScholarship($p->cycle, $p->degree_level_id, $user->id))]);
        $docsMissing = $docs->sum(fn ($d) => count($d['missing']));
        $saved = $user->savedScholarships()->with(['cycle.programme', 'cycle.currentDeadlines'])->get();

        // Upcoming confirmed deadlines across saved and active plans, nearest first. Unconfirmed ones are listed separately.
        $tracked = $saved->pluck('cycle')->merge($plans->whereIn('status', [PlanStatus::Interested, PlanStatus::Preparing, PlanStatus::ReadyForReview])->pluck('cycle'))->unique('id');
        $upcoming = $tracked->filter(fn ($c) => $deadlines->nextClosing($c) !== null)->sortBy(fn ($c) => $deadlines->nextClosing($c)->getTimestamp())->take(5);
        $unconfirmed = $tracked->filter(fn ($c) => $deadlines->nextClosing($c) === null && $c->effectiveStatus()->value !== 'closed');

        return view('student.dashboard', [
            'user' => $user, 'profile' => $profile,
            'missing' => $profile ? $profile->missingFields() : (new StudentProfile)->missingFields(),
            'formPercent' => $formPercent, 'docs' => $docs, 'docsMissing' => $docsMissing,
            'cases' => ServiceCase::where('user_id', $user->id)->with('invoices')->latest('id')->get(),
            'plans' => $plans, 'savedCount' => $saved->count(), 'upcoming' => $upcoming, 'unconfirmed' => $unconfirmed,
            'pendingScans' => DocumentVersion::whereHas('document', fn ($q) => $q->where('user_id', $user->id))->where('scan_status', ScanStatus::Pending->value)->count(),
            'unread' => $user->unreadNotifications()->count(),
            'enquiries' => $user->enquiries()->latest()->limit(3)->get(),
            'svc' => $deadlines,
        ]);
    }
}
