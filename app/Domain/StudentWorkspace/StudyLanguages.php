<?php

namespace App\Domain\StudentWorkspace;

use App\Models\ScholarshipCycle;

/** The languages a student can choose to study in for a scholarship: English, and the official language(s) of the country offering it. */
class StudyLanguages
{
    /** @return list<string> English first, then each official language of the scholarship's destination country(ies), without repeats. */
    public function optionsFor(ScholarshipCycle $cycle): array
    {
        $countries = $cycle->destinations->isNotEmpty() ? $cycle->destinations : collect([$cycle->programme->hostCountry])->filter();
        $options = ['English'];
        foreach ($countries as $country) {
            foreach ($country->officialLanguages() as $language) {
                in_array($language, $options, true) || $options[] = $language;
            }
        }

        return $options;
    }
}
