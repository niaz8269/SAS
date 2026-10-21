<?php

namespace App\Domain\StudentWorkspace;

use App\Enums\MatchResult;
use App\Models\ScholarshipCycle;
use App\Models\StudentProfile;

/**
 * MATCH-01 / MATCH-02. Deterministic, explainable rule indications. This is NOT a probability of winning and unknown
 * data stays unknown. A known mandatory mismatch is never offset by a positive feature (e.g. preferred destination).
 */
class MatchingService
{
    public const RULE_VERSION = 'r1.0';

    /** @return array{result:MatchResult,criteria:list<array{name:string,status:string,explanation:string}>,rule_version:string} */
    public function evaluate(?StudentProfile $profile, ScholarshipCycle $cycle): array
    {
        $cycle->loadMissing(['nationalities', 'degreeLevels', 'subjects', 'criteria']);
        $criteria = [
            $this->nationality($profile, $cycle),
            $this->degree($profile, $cycle),
            $this->subject($profile, $cycle),
        ];
        foreach ($cycle->criteria as $c) {
            $criteria[] = match ($c->type) {
                'min_gpa' => $this->gpa($profile, $c),
                'language' => $this->language($profile, $c),
                'age' => $this->age($profile, $c),
                default => $this->row(ucfirst($c->type), 'unknown', 'This criterion cannot be checked automatically.'),
            };
        }

        $mandatoryFail = collect($criteria)->contains(fn ($r) => $r['status'] === 'not_met' && ($r['mandatory'] ?? true));
        $unknown = collect($criteria)->contains('status', 'unknown');
        $result = $mandatoryFail ? MatchResult::CriterionNotMet : ($unknown ? MatchResult::MoreInformation : MatchResult::PotentialMatch);

        return ['result' => $result, 'criteria' => $criteria, 'rule_version' => self::RULE_VERSION];
    }

    private function row(string $name, string $status, string $explanation, bool $mandatory = true): array
    {
        return compact('name', 'status', 'explanation', 'mandatory');
    }

    private function nationality(?StudentProfile $p, ScholarshipCycle $c): array
    {
        if ($c->any_nationality) {
            return $this->row('Nationality', 'met', 'Open to applicants of any nationality (as published).');
        }
        if ($c->nationalities->isEmpty()) {
            return $this->row('Nationality', 'unknown', 'Eligible nationalities are not confirmed for this call.');
        }
        if (! $p?->nationality_country_id) {
            return $this->row('Nationality', 'unknown', 'Add your nationality to your profile to check this. We do not infer it from your residence.');
        }

        return $c->nationalities->contains('id', $p->nationality_country_id)
            ? $this->row('Nationality', 'met', 'Your nationality is listed as eligible.')
            : $this->row('Nationality', 'not_met', 'Your nationality is not in the published eligible list.');
    }

    private function degree(?StudentProfile $p, ScholarshipCycle $c): array
    {
        if ($c->degreeLevels->isEmpty()) {
            return $this->row('Degree level', 'unknown', 'Degree levels are not confirmed for this call.');
        }
        if (! $p?->desired_degree_level_id) {
            return $this->row('Degree level', 'unknown', 'Add your desired degree level to your profile.');
        }

        return $c->degreeLevels->contains('id', $p->desired_degree_level_id)
            ? $this->row('Degree level', 'met', 'Your desired degree level is offered.')
            : $this->row('Degree level', 'not_met', 'Your desired degree level is not offered in this call.');
    }

    private function subject(?StudentProfile $p, ScholarshipCycle $c): array
    {
        if ($c->subjects->isEmpty()) {
            return $this->row('Subject', 'unknown', 'Subject restrictions are not confirmed for this call.');
        }
        $interests = $p?->subject_interest_ids ?? [];
        if ($interests === []) {
            return $this->row('Subject', 'unknown', 'Add subject interests to your profile.');
        }

        return $c->subjects->pluck('id')->intersect($interests)->isNotEmpty()
            ? $this->row('Subject', 'met', 'At least one of your subject interests is covered.')
            : $this->row('Subject', 'not_met', 'None of your subject interests are in the published subject list.');
    }

    private function gpa(?StudentProfile $p, $crit): array
    {
        $name = 'Academic grade';
        if (! $p || blank($p->gpa_value) || blank($p->gpa_scale)) {
            return $this->row($name, 'unknown', "Required: {$crit->operator} {$crit->value} ({$crit->unit}). Add your grade and its scale.", (bool) $crit->mandatory);
        }
        // STU-02: no conversion between scales without an approved conversion source.
        if (mb_strtolower(trim($p->gpa_scale)) !== mb_strtolower(trim((string) $crit->unit)) || ! is_numeric($p->gpa_value)) {
            return $this->row($name, 'unknown', "Your grade is recorded as '{$p->gpa_scale}' but the requirement uses '{$crit->unit}'. We do not convert between scales.", (bool) $crit->mandatory);
        }
        $ok = $this->compare((float) $p->gpa_value, $crit->operator, (float) $crit->value);

        return $this->row($name, $ok ? 'met' : 'not_met', $ok ? 'Your grade meets the published threshold.' : "Your grade is below the published threshold ({$crit->operator} {$crit->value}).", (bool) $crit->mandatory);
    }

    private function language(?StudentProfile $p, $crit): array
    {
        $name = 'Language: '.$crit->unit;
        $entry = collect($p?->language_evidence ?? [])->first(fn ($e) => mb_strtolower($e['test'] ?? '') === mb_strtolower((string) $crit->unit));
        if (! $entry || ! is_numeric($entry['score'] ?? null)) {
            return $this->row($name, 'unknown', "Required: {$crit->operator} {$crit->value}. No matching score on your profile yet.", (bool) $crit->mandatory);
        }
        $ok = $this->compare((float) $entry['score'], $crit->operator, (float) $crit->value);

        return $this->row($name, $ok ? 'met' : 'not_met', $ok ? 'Your score meets the published minimum.' : "Your score is below the published minimum ({$crit->value}).", (bool) $crit->mandatory);
    }

    private function age(?StudentProfile $p, $crit): array
    {
        if (! $p?->date_of_birth) {
            return $this->row('Age', 'unknown', 'An age condition exists. Date of birth is optional; add it only if you want this checked.', (bool) $crit->mandatory);
        }
        $age = $p->date_of_birth->age;
        $ok = $this->compare((float) $age, $crit->operator, (float) $crit->value);

        return $this->row('Age', $ok ? 'met' : 'not_met', $ok ? 'Meets the age condition as of today. Check the official reference date.' : 'Does not meet the age condition as of today. Check the official reference date.', (bool) $crit->mandatory);
    }

    private function compare(float $a, string $op, float $b): bool
    {
        return match ($op) {
            '>=' => $a >= $b, '<=' => $a <= $b, '>' => $a > $b, '<' => $a < $b, default => abs($a - $b) < 1e-9,
        };
    }
}
