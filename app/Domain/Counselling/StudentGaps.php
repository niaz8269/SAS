<?php

namespace App\Domain\Counselling;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Documents\Requirements;
use App\Models\ApplicationForm;
use App\Models\ApplicationPlan;
use App\Models\User;

/** What is still missing for a student: unanswered required form questions, and mandatory documents per unfinished application. */
class StudentGaps
{
    public function __construct(private ApplicationFormService $forms, private Requirements $requirements) {}

    /** @return array{form:list<string>,applications:list<array{plan:ApplicationPlan,missing:list<string>}>,count:int} */
    public function for(User $student): array
    {
        $form = ApplicationForm::where('user_id', $student->id)->first() ?? new ApplicationForm(['data' => []]);
        $formMissing = $this->forms->missing($form);
        $applications = ApplicationPlan::where('user_id', $student->id)->whereIn('stage', ['draft', 'returned'])->with('cycle.programme')->get()
            ->map(fn (ApplicationPlan $p) => ['plan' => $p, 'missing' => $this->requirements->progress($this->requirements->forPlan($p))['missing']])
            ->filter(fn ($row) => $row['missing'] !== [])->values()->all();

        return ['form' => $formMissing, 'applications' => $applications, 'count' => count($formMissing) + array_sum(array_map(fn ($a) => count($a['missing']), $applications))];
    }

    /** The reminder text sent to the student. */
    public function reminder(User $student, array $gaps): string
    {
        $lines = ["Hello {$student->name}, to move your applications forward we still need the following."];
        if ($gaps['form']) {
            $lines[] = 'Application form: '.implode(', ', $gaps['form']).'.';
        }
        foreach ($gaps['applications'] as $row) {
            $lines[] = $row['plan']->cycle->programme->title.': '.implode(', ', $row['missing']).'.';
        }
        $lines[] = 'You can add these in your SAS workspace. Reply here if anything is unclear.';

        return implode("\n", $lines);
    }
}
