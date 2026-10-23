<?php

namespace App\Http\Controllers\Student;

use App\Domain\Applications\ApplicationFormService;
use App\Http\Controllers\Concerns\ActsForStudent;
use App\Http\Controllers\Controller;
use App\Models\ApplicationPlan;
use App\Models\Country;
use Illuminate\Http\Request;

/**
 * The master application form: one set of answers for every scholarship (step 2 of an application, or on its own).
 * The whole form is saved at once; saving never requires every field.
 */
class ApplicationFormController extends Controller
{
    use ActsForStudent;

    public function show(Request $request, ApplicationFormService $forms, ?ApplicationPlan $plan = null)
    {
        $student = $this->student($request);
        if ($plan) {
            $this->authorize('view', $plan);
            $plan->load('cycle.programme');
        }
        $form = $forms->formFor($student);

        return view('student.application-form', [
            'form' => $form, 'sections' => $forms->sections(), 'progress' => $forms->sectionProgress($form), 'missing' => $forms->missing($form), 'percent' => $forms->percent($form),
            'countries' => Country::orderBy('name')->pluck('name'), 'plan' => $plan, 'cycle' => $plan?->cycle, 'step' => $plan ? 2 : 0, 'student' => $student, 'locked' => $plan && ! $plan->isEditable(),
        ]);
    }

    public function update(Request $request, ApplicationFormService $forms, ?ApplicationPlan $plan = null)
    {
        $student = $this->student($request);
        if ($plan) {
            $this->authorize('update', $plan);
            abort_unless($plan->isEditable(), 403);
        }
        $rules = ['sections' => ['nullable', 'array'], 'declared' => ['nullable', 'boolean']];
        foreach (array_keys($forms->sections()) as $key) {
            foreach ($forms->rules($key) as $path => $rule) {
                $rules['sections.'.$key.'.'.$path] = $rule;
            }
        }
        $input = $request->validate($rules);
        $present = collect($input['sections'] ?? [])->only(array_keys($forms->sections()))->all();
        $forms->saveAll($student, $present, $request->user());
        $forms->declare($student, $request->boolean('declared'));

        $ctx = $this->ctx($request);

        return redirect()->to($plan ? $ctx->route('applications.form', $plan) : $ctx->route('form.show'))->with('status', 'Saved.');
    }
}
