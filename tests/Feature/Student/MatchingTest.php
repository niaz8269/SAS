<?php

namespace Tests\Feature\Student;

use App\Domain\StudentWorkspace\MatchingService;
use App\Enums\MatchResult;
use App\Models\Country;
use App\Models\CycleCriterion;
use App\Models\DegreeLevel;
use App\Models\ScholarshipCycle;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingTest extends TestCase
{
    use RefreshDatabase;

    private function profile(array $attrs = []): StudentProfile
    {
        return StudentProfile::create(['user_id' => User::factory()->create()->id] + $attrs);
    }

    private function names(array $result, string $status): array
    {
        return collect($result['criteria'])->where('status', $status)->pluck('name')->all();
    }

    public function test_missing_language_score_gives_more_information_needed_not_eligibility(): void
    {
        $cycle = ScholarshipCycle::factory()->published()->create(['any_nationality' => true]);
        CycleCriterion::create(['cycle_id' => $cycle->id, 'type' => 'language', 'operator' => '>=', 'value' => '6.5', 'unit' => 'IELTS']);
        $result = app(MatchingService::class)->evaluate($this->profile(), $cycle);

        $this->assertSame(MatchResult::MoreInformation, $result['result']);
        $this->assertContains('Language: IELTS', $this->names($result, 'unknown'));
    }

    public function test_nationality_mismatch_is_explained_and_not_offset_by_other_positives(): void
    {
        $uk = Country::factory()->create();
        $pk = Country::factory()->create();
        $degree = DegreeLevel::factory()->create();
        $cycle = ScholarshipCycle::factory()->published()->create();
        $cycle->nationalities()->attach($uk);
        $cycle->degreeLevels()->attach($degree);

        $profile = $this->profile(['nationality_country_id' => $pk->id, 'desired_degree_level_id' => $degree->id, 'preferred_destination_ids' => [1]]);
        $result = app(MatchingService::class)->evaluate($profile, $cycle);

        $this->assertSame(MatchResult::CriterionNotMet, $result['result']);
        $this->assertContains('Nationality', $this->names($result, 'not_met'));
        $this->assertContains('Degree level', $this->names($result, 'met'));
    }

    public function test_all_known_criteria_met_gives_potential_match_with_no_probability(): void
    {
        $country = Country::factory()->create();
        $degree = DegreeLevel::factory()->create();
        $cycle = ScholarshipCycle::factory()->published()->create();
        $cycle->nationalities()->attach($country);
        $cycle->degreeLevels()->attach($degree);
        $profile = $this->profile(['nationality_country_id' => $country->id, 'desired_degree_level_id' => $degree->id]);
        // Subject restrictions not confirmed => unknown, so overall stays "more information".
        $this->assertSame(MatchResult::MoreInformation, app(MatchingService::class)->evaluate($profile, $cycle)['result']);

        $subject = Subject::create(['name' => 'Physics', 'slug' => 'physics']);
        $cycle->subjects()->attach($subject);
        $profile->update(['subject_interest_ids' => [$subject->id]]);
        $result = app(MatchingService::class)->evaluate($profile->fresh(), $cycle->fresh());
        $this->assertSame(MatchResult::PotentialMatch, $result['result']);
        $this->assertArrayNotHasKey('probability', $result);
        $this->assertArrayNotHasKey('score', $result);
    }

    public function test_grades_in_a_different_scale_are_never_converted(): void
    {
        $cycle = ScholarshipCycle::factory()->published()->create(['any_nationality' => true]);
        CycleCriterion::create(['cycle_id' => $cycle->id, 'type' => 'min_gpa', 'operator' => '>=', 'value' => '3.0', 'unit' => 'CGPA /4.0']);

        $other = $this->profile(['gpa_value' => '85', 'gpa_scale' => 'Percentage']);
        $r = app(MatchingService::class)->evaluate($other, $cycle);
        $this->assertContains('Academic grade', $this->names($r, 'unknown'));

        $low = $this->profile(['gpa_value' => '2.5', 'gpa_scale' => 'CGPA /4.0']);
        $this->assertContains('Academic grade', $this->names(app(MatchingService::class)->evaluate($low, $cycle), 'not_met'));
        $ok = $this->profile(['gpa_value' => '3.4', 'gpa_scale' => 'cgpa /4.0']);
        $this->assertContains('Academic grade', $this->names(app(MatchingService::class)->evaluate($ok, $cycle), 'met'));
    }

    public function test_nationality_is_never_inferred_from_residence(): void
    {
        $uk = Country::factory()->create();
        $cycle = ScholarshipCycle::factory()->published()->create();
        $cycle->nationalities()->attach($uk);
        $profile = $this->profile(['residence_country_id' => $uk->id]);
        $this->assertContains('Nationality', $this->names(app(MatchingService::class)->evaluate($profile, $cycle), 'unknown'));
    }

    public function test_a_guest_without_a_profile_gets_unknowns_not_errors(): void
    {
        $cycle = ScholarshipCycle::factory()->published()->create();
        $this->assertSame(MatchResult::MoreInformation, app(MatchingService::class)->evaluate(null, $cycle)['result']);
    }
}
