<?php

namespace App\Domain\Counselling;

use App\Enums\ApplicationStatus;
use App\Models\ApplicationForm;
use App\Models\ApplicationPlan;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Illuminate\Support\Collection;

/** Suggests open scholarships from a student's goals (countries, degree, subject), skipping ones they already applied to. */
class ScholarshipMatcher
{
    /**
     * What to match on: the counsellor's goals for the student where they wrote them, otherwise what the student put in their own
     * application form (the degree they apply as, and the discipline and major).
     *
     * @return array{0:list<string>,1:string,2:string} lower-case countries, degree, subject words
     */
    public function criteria(User $student): array
    {
        $goal = $student->goals;
        $form = ApplicationForm::where('user_id', $student->id)->first();
        $degree = filled($goal?->degree) ? $goal->degree : (string) $form?->value('apply_as');
        $subject = filled($goal?->subject) ? $goal->subject : trim($form?->value('discipline').' '.$form?->value('major'));

        return [array_map('mb_strtolower', $goal?->countryList() ?? []), mb_strtolower(trim((string) $degree)), mb_strtolower(trim($subject))];
    }

    /** @return Collection<int,array{cycle:ScholarshipCycle,score:int,why:list<string>}> best first */
    public function for(User $student, int $limit = 10): Collection
    {
        [$countries, $degree, $subject] = $this->criteria($student);
        if ($countries === [] && $degree === '' && $subject === '') {
            return collect();
        }
        $applied = ApplicationPlan::where('user_id', $student->id)->pluck('cycle_id')->all();
        $words = collect(preg_split('/[\s,;]+/', $subject))->filter(fn ($w) => mb_strlen($w) >= 4)->all();

        return ScholarshipCycle::visible()->with(['programme', 'degreeLevels', 'destinations'])->whereNotIn('id', $applied)->get()
            ->reject(fn (ScholarshipCycle $c) => $c->effectiveStatus() === ApplicationStatus::Closed)
            ->map(function (ScholarshipCycle $c) use ($countries, $degree, $words) {
                $score = 0;
                $why = [];
                $places = $c->destinations->pluck('name')->map(fn ($n) => mb_strtolower($n));
                if ($countries && $places->intersect($countries)->isNotEmpty()) {
                    $score += 3;
                    $why[] = 'in '.$c->destinations->pluck('name')->implode(', ');
                }
                if ($degree !== '' && $c->degreeLevels->contains(fn ($l) => str_contains($degree, mb_strtolower($l->name)) || str_contains(mb_strtolower($l->name), $degree))) {
                    $score += 2;
                    $why[] = $degree;
                }
                $title = mb_strtolower($c->programme->title.' '.($c->programme->summary ?? ''));
                if ($words && collect($words)->contains(fn ($w) => str_contains($title, $w))) {
                    $score += 1;
                    $why[] = 'subject';
                }

                return ['cycle' => $c, 'score' => $score, 'why' => $why];
            })->filter(fn ($row) => $row['score'] > 0)->sortByDesc('score')->take($limit)->values();
    }
}
