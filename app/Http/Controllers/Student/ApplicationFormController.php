<?php

namespace App\Http\Controllers\Student;

use App\Domain\Applications\ApplicationFormService;
use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

/** The student's master application form. Answers are saved section by section; saving never requires every field. */
class ApplicationFormController extends Controller
{
    public function show(Request $request, ApplicationFormService $forms)
    {
        $form = $forms->formFor($request->user());

        return view('student.application-form', [
            'form' => $form, 'sections' => $forms->sections(), 'missing' => $forms->missing($form), 'percent' => $forms->percent($form),
            'countries' => Country::orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request, string $section, ApplicationFormService $forms)
    {
        $forms->section($section); // 404 for an unknown section
        $forms->save($request->user(), $section, $request->validate($forms->rules($section)));

        return redirect()->to(route('student.form.show').'#'.$section)->with('status', $forms->section($section)['title'].' saved.');
    }

    public function declare(Request $request, ApplicationFormService $forms)
    {
        $forms->declare($request->user(), $request->boolean('agreed'));

        return redirect()->to(route('student.form.show').'#declaration')->with('status', $request->boolean('agreed') ? 'Declaration recorded.' : 'Declaration withdrawn.');
    }
}
