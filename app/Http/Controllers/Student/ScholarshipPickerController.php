<?php

namespace App\Http\Controllers\Student;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Concerns\ActsForStudent;
use App\Http\Controllers\Controller;
use App\Models\DegreeLevel;
use App\Models\ScholarshipCycle;
use Illuminate\Http\Request;

/** Where a student chooses the scholarships to apply for. Choosing one opens its application with the documents it needs. */
class ScholarshipPickerController extends Controller
{
    use ActsForStudent;

    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:80'], 'level' => ['nullable', 'string', 'max:24'], 'show_closed' => ['nullable', 'boolean']]);
        $q = trim((string) ($filters['q'] ?? ''));

        $cycles = ScholarshipCycle::visible()->with(['programme.funder', 'degreeLevels', 'destinations', 'currentDeadlines'])
            ->when($q !== '', fn ($query) => $query->whereHas('programme', fn ($p) => $p->where('title', 'like', '%'.$q.'%')))
            ->when($filters['level'] ?? null, fn ($query, $code) => $query->whereHas('degreeLevels', fn ($l) => $l->where('degree_levels.code', $code)))
            ->get()
            ->when(! $request->boolean('show_closed'), fn ($c) => $c->reject(fn ($cycle) => $cycle->effectiveStatus() === ApplicationStatus::Closed))
            ->sortBy(fn ($c) => mb_strtolower($c->programme->title))->values();

        return view('student.choose-scholarships', [
            'cycles' => $cycles, 'filters' => ['q' => $q, 'level' => $filters['level'] ?? '', 'show_closed' => $request->boolean('show_closed')],
            'levels' => DegreeLevel::orderBy('sort')->get(), 'student' => $this->student($request),
            'planned' => $this->student($request)->applicationPlans()->pluck('public_id', 'cycle_id')->all(),
        ]);
    }
}
