<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use Illuminate\Http\Request;

/** The student's view of the applications SAS supports for them, and the fees on each. Read only: staff record every change. */
class CaseController extends Controller
{
    public function index(Request $request)
    {
        $cases = ServiceCase::where('user_id', $request->user()->id)->with(['advisor', 'invoices', 'cycle.programme'])->latest('id')->get();

        return view('student.cases.index', ['cases' => $cases]);
    }

    public function show(Request $request, ServiceCase $case)
    {
        $this->authorize('view', $case);
        abort_unless($case->user_id === $request->user()->id, 404); // staff use the admin panel, not the student workspace

        return view('student.cases.show', ['case' => $case->load(['advisor', 'invoices', 'cycle.programme'])]);
    }
}
