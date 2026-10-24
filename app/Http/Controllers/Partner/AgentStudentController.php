<?php

namespace App\Http\Controllers\Partner;

use App\Domain\Agents\AgentStudents;
use App\Domain\Applications\ApplicationFormService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\StudentContext;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** A recruiting agent's students: add one or many, edit their basic details, and open their applications. Only the agent's own students are reachable. */
class AgentStudentController extends Controller
{
    public function index(Request $request)
    {
        $org = $request->user()->partnerOrganisation;
        $q = trim((string) $request->query('q', ''));
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100], true) ? (int) $request->query('per_page') : 25;
        [$sort, $dir] = [in_array($request->query('sort'), ['id', 'name', 'email'], true) ? $request->query('sort') : 'id', $request->query('dir') === 'asc' ? 'asc' : 'desc'];
        $students = $org->students()->withCount('applicationPlans')->with('applicationForm')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')))
            ->orderBy($sort, $dir)->paginate($perPage)->withQueryString();

        return view('partner.agent.students.index', ['organisation' => $org, 'students' => $students, 'q' => $q, 'perPage' => $perPage, 'sort' => $sort, 'dir' => $dir]);
    }

    public function create(AgentStudents $agents)
    {
        return view('partner.agent.students.form', ['student' => null, 'countries' => $agents->countries(), 'consent' => AgentStudents::CONSENT]);
    }

    public function store(Request $request, AgentStudents $agents)
    {
        $data = $request->validate($agents->rules() + ['consent' => ['accepted']], ['consent.accepted' => 'Please confirm the student agreed.']);
        try {
            $student = $agents->add($request->user()->partnerOrganisation, $request->user(), collect($data)->except('consent')->all());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        return redirect()->route('partner.student.show', $student->public_id)->with('status', 'Student added. They were emailed an invitation to choose their own password.');
    }

    public function show(Request $request, User $student, ApplicationFormService $forms)
    {
        return view('partner.agent.students.show', [
            'student' => $student, 'plans' => $student->applicationPlans()->with('cycle.programme')->latest()->get(),
            'formPercent' => $forms->percent($forms->formFor($student)), 'ctx' => app(StudentContext::class),
            'messages' => $student->agentMessages()->with('author')->oldest('id')->get(),
        ]);
    }

    public function edit(Request $request, User $student, AgentStudents $agents, ApplicationFormService $forms)
    {
        $form = $forms->formFor($student);

        return view('partner.agent.students.form', ['student' => $student, 'form' => $form, 'countries' => $agents->countries(), 'consent' => null, 'ctx' => app(StudentContext::class)]);
    }

    public function update(Request $request, User $student, AgentStudents $agents)
    {
        $data = $request->validate($agents->rules());
        try {
            $agents->update($student, $request->user(), $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        return redirect()->route('partner.student.show', $student->public_id)->with('status', 'Saved.');
    }

    public function invite(Request $request, User $student, AgentStudents $agents)
    {
        $agents->invite($student, $request->user()->partnerOrganisation);

        return back()->with('status', 'Invitation sent to '.$student->email.'.');
    }

    public function importForm()
    {
        return view('partner.agent.students.import');
    }

    public function template()
    {
        return response("surname,name,email,country,passport_number,date_of_birth\nKhan,Amina,amina@example.org,Pakistan,AB1234567,2001-05-14\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="sas-students-template.csv"']);
    }

    public function import(Request $request, AgentStudents $agents)
    {
        $request->validate(['file' => ['required', 'file', 'max:512', 'mimes:csv,txt'], 'consent' => ['accepted']], ['consent.accepted' => 'Please confirm the students agreed.']);
        try {
            $report = $agents->importCsv($request->user()->partnerOrganisation, $request->user(), (string) file_get_contents($request->file('file')->getRealPath()));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('partner.students.import')->with('status', $report['added'].' '.($report['added'] === 1 ? 'student' : 'students').' added and invited.')->with('skipped', $report['skipped']);
    }
}
