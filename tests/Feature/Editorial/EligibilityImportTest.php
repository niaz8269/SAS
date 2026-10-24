<?php

namespace Tests\Feature\Editorial;

use App\Domain\Scholarship\ScholarshipSearch;
use App\Domain\StudentWorkspace\MatchingService;
use App\Models\Country;
use App\Models\ScholarshipCycle;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class EligibilityImportTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
    }

    private function run_(array $entries, array $options = []): int
    {
        $path = tempnam(sys_get_temp_dir(), 'elig').'.json';
        file_put_contents($path, json_encode($entries));

        return $this->artisan('sas:import-eligibility', ['files' => [$path]] + $options)->run();
    }

    private function entry(ScholarshipCycle $cycle, array $over = []): array
    {
        return array_merge([
            'external_key' => $cycle->external_key, 'checked_on' => '2026-10-24', 'status' => 'confirmed',
            'sources' => [['url' => 'https://www.example-ministry.gov/scholarship', 'publisher' => 'Example Ministry', 'publisher_type' => 'government']],
            'nationality' => ['mode' => 'any', 'excluded_codes' => ['GB']], 'subjects' => ['mode' => 'any'],
            'eligibility_notes' => 'Open to citizens of any country except the host country.',
            'criteria' => [['type' => 'language', 'operator' => '>=', 'value' => '6.5', 'unit' => 'IELTS Academic', 'mandatory' => true, 'evidence' => 'IELTS 6.5 overall']],
            'benefits' => [['type' => 'tuition', 'coverage' => 'covered'], ['type' => 'living_allowance', 'coverage' => 'covered', 'limit_text' => 'monthly', 'amount' => 1300, 'currency' => 'GBP']],
            'requirements' => [['document_type' => 'passport', 'text' => 'A copy of your passport'], ['text' => 'Two references', 'conditional_rule' => 'for doctoral applicants', 'mandatory' => false]],
        ], $over);
    }

    public function test_it_adds_eligibility_funding_and_documents_with_their_sources_and_a_rerun_changes_nothing(): void
    {
        $cycle = $this->publishedCycle(['external_key' => 'xx-test-2027']);

        $this->assertSame(0, $this->run_([$this->entry($cycle)]));

        $cycle->refresh();
        $this->assertTrue($cycle->any_nationality);
        $this->assertTrue($cycle->any_subject);
        $this->assertSame(['GB'], $cycle->excludedNationalities()->pluck('code')->all());
        $this->assertStringContainsString('except the host country', $cycle->eligibility_notes);
        $this->assertSame(1, $cycle->criteria()->count());
        $this->assertSame(['tuition', 'living_allowance'], $cycle->benefits()->pluck('type')->all());
        $this->assertSame(130000, (int) $cycle->benefits()->where('type', 'living_allowance')->value('amount_minor'));
        $this->assertSame(2, $cycle->requirements()->count());
        $this->assertFalse((bool) $cycle->requirements()->where('text', 'Two references')->value('mandatory'));
        $this->assertTrue($cycle->evidence()->where('url', 'https://www.example-ministry.gov/scholarship')->exists());
        $this->assertFalse((bool) $cycle->fully_funded_verified, 'full funding is never verified by an import');

        $this->get(route('scholarships.show', [$cycle->programme->slug, $cycle->slug]))->assertOk()
            ->assertSee('Any nationality except', false)->assertSee('Any subject')->assertSee('A copy of your passport')->assertSee('Covered');

        $this->run_([$this->entry($cycle)]);
        $this->assertSame([1, 2, 2], [$cycle->criteria()->count(), $cycle->benefits()->count(), $cycle->requirements()->count()]);
        $this->assertSame(1, $cycle->evidence()->where('url', 'https://www.example-ministry.gov/scholarship')->count());
    }

    public function test_search_respects_any_nationality_exclusions_and_any_subject(): void
    {
        $open = $this->publishedCycle(['external_key' => 'xx-open-2027']);
        $unknown = $this->publishedCycle(['external_key' => 'xx-unknown-2027']);
        $listed = $this->publishedCycle(['external_key' => 'xx-listed-2027']);
        $this->run_([
            $this->entry($open),
            $this->entry($listed, ['nationality' => ['mode' => 'list', 'codes' => ['PK', 'IN']], 'subjects' => ['mode' => 'list', 'slugs' => ['engineering']]]),
        ]);
        $search = fn (array $f) => app(ScholarshipSearch::class)->query($f)->pluck('scholarship_cycles.id')->all();
        $slug = fn (string $code) => Country::where('code', $code)->value('slug');

        $this->assertEqualsCanonicalizing([$open->id, $listed->id], $search(['nationality' => [$slug('PK')]]));
        $this->assertSame([$open->id], $search(['nationality' => [$slug('FR')]]), 'a call that lists other countries is not shown');
        $this->assertSame([], $search(['nationality' => [$slug('GB')]]), 'an excluded nationality does not see the call it cannot apply to');
        $this->assertNotContains($unknown->id, $search(['nationality' => [$slug('PK')]]), 'unconfirmed eligibility is never matched');
        $this->assertEqualsCanonicalizing([$open->id, $listed->id], $search(['subject' => ['engineering']]), 'a call open to any subject is in every subject search');
        $this->assertSame([$open->id], $search(['subject' => ['law']]));
    }

    public function test_the_match_says_no_to_an_excluded_nationality_and_yes_to_any_subject(): void
    {
        $cycle = $this->publishedCycle(['external_key' => 'xx-match-2027']);
        $this->run_([$this->entry($cycle)]);
        $student = User::factory()->create();
        StudentProfile::create(['user_id' => $student->id, 'nationality_country_id' => Country::where('code', 'GB')->value('id')]);

        $rows = collect(app(MatchingService::class)->evaluate($student->fresh('studentProfile')->studentProfile, $cycle->fresh())['criteria'] ?? [])->keyBy('name');

        $this->assertSame('not_met', $rows['Nationality']['status'] ?? null);
        $this->assertSame('met', $rows['Subject']['status'] ?? null);
    }

    public function test_it_never_guesses_and_reports_what_it_skipped(): void
    {
        $cycle = $this->publishedCycle(['external_key' => 'xx-guard-2027', 'funding_type' => 'partial']);
        $before = $cycle->only(['any_nationality', 'funding_type']);

        $this->run_([
            $this->entry($cycle, [
                'nationality' => ['mode' => 'unconfirmed'], 'subjects' => ['mode' => 'unconfirmed'], 'funding' => ['type' => 'full'],
                'sources' => [['url' => 'https://scholarship-portal.example/blog', 'publisher' => 'A portal', 'publisher_type' => 'aggregator'], ['url' => 'http://localhost/x', 'publisher_type' => 'government']],
                'criteria' => [['type' => 'essay', 'operator' => '>=', 'value' => '5'], ['type' => 'age', 'operator' => '<=', 'value' => 'young']],
                'benefits' => [['type' => 'jetski', 'coverage' => 'covered']], 'requirements' => [['text' => '']],
            ]),
            ['external_key' => 'xx-does-not-exist', 'checked_on' => '2026-10-24'],
        ]);

        $cycle->refresh();
        $this->assertSame($before['any_nationality'], $cycle->any_nationality, 'unconfirmed leaves the field alone');
        $this->assertFalse($cycle->any_subject);
        $this->assertSame('partial', $cycle->funding_type, 'a recorded funding type is never changed by an import');
        $this->assertSame(0, $cycle->criteria()->count() + $cycle->benefits()->count() + $cycle->requirements()->count(), 'invalid structured rows are dropped');
        $this->assertSame(0, $cycle->evidence()->where('url', 'like', '%scholarship-portal%')->count(), 'an aggregator is not a source');
        $this->assertSame(0, $cycle->evidence()->where('url', 'like', '%localhost%')->count());
    }

    public function test_only_an_unknown_funding_type_becomes_known_and_a_dry_run_changes_nothing(): void
    {
        $cycle = $this->publishedCycle(['external_key' => 'xx-fund-2027', 'funding_type' => 'unknown']);

        $this->run_([$this->entry($cycle, ['funding' => ['type' => 'tuition_only', 'summary' => 'Tuition fees are paid.']])], ['--dry-run' => true]);
        $this->assertSame('unknown', $cycle->refresh()->funding_type);
        $this->assertFalse($cycle->any_nationality);

        $this->run_([$this->entry($cycle, ['funding' => ['type' => 'tuition_only', 'summary' => 'Tuition fees are paid.']])]);
        $this->assertSame('tuition_only', $cycle->refresh()->funding_type);
    }

    public function test_the_homepage_search_and_the_directory_offer_a_nationality(): void
    {
        $this->get('/')->assertOk()->assertSee('Your nationality')->assertSee('name="nationality[]"', false);
    }
}
