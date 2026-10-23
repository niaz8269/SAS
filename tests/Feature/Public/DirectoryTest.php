<?php

namespace Tests\Feature\Public;

use App\Domain\Editorial\CycleWorkflow;
use App\Domain\Scholarship\SearchIndexer;
use App\Enums\Role;
use App\Livewire\ScholarshipDirectory;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\Funder;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\SourceEvidence;
use App\Models\Subject;
use App\Models\SubjectAlias;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class DirectoryTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    public function test_directory_is_readable_without_registration(): void
    {
        $this->get('/scholarships')->assertOk()->assertSee('Scholarship directory');
        $this->get('/')->assertOk()->assertSee('Scholarships Application Services');
    }

    public function test_search_china_masters_fully_funded_returns_only_matching_published_cycles(): void
    {
        $china = Country::factory()->destination()->create(['slug' => 'china', 'name' => 'China']);
        $germany = Country::factory()->destination()->create(['slug' => 'germany', 'name' => 'Germany']);
        $master = DegreeLevel::factory()->create(['slug' => 'master', 'name' => 'Master']);
        $bachelor = DegreeLevel::factory()->create(['slug' => 'bachelor', 'name' => 'Bachelor']);

        $match = $this->publishedCycle(['funding_type' => 'full', 'fully_funded_verified' => true], $this->exactDeadline(), $china, $master);
        $unverifiedFull = $this->publishedCycle(['funding_type' => 'full', 'fully_funded_verified' => false], $this->exactDeadline(), $china, $master);
        $wrongDegree = $this->publishedCycle(['funding_type' => 'full', 'fully_funded_verified' => true], $this->exactDeadline(), $china, $bachelor);
        $wrongCountry = $this->publishedCycle(['funding_type' => 'full', 'fully_funded_verified' => true], $this->exactDeadline(), $germany, $master);
        $draft = ScholarshipCycle::factory()->create(['funding_type' => 'full', 'fully_funded_verified' => true]);
        $draft->destinations()->attach($china);
        $draft->degreeLevels()->attach($master);

        $component = Livewire::test(ScholarshipDirectory::class)
            ->set('destination', ['china'])->set('degree', ['master'])->set('funding', ['full']);

        $component->assertSee($match->programme->title)
            ->assertDontSee($unverifiedFull->programme->title)   // "Fully funded" requires verified benefits
            ->assertDontSee($wrongDegree->programme->title)
            ->assertDontSee($wrongCountry->programme->title)
            ->assertDontSee($draft->programme->title);            // drafts never leak
    }

    public function test_filters_are_in_the_url_so_a_shared_link_recreates_the_results(): void
    {
        $china = Country::factory()->destination()->create(['slug' => 'china']);
        $other = Country::factory()->destination()->create(['slug' => 'elsewhere']);
        $in = $this->publishedCycle([], $this->exactDeadline(), $china);
        $out = $this->publishedCycle([], $this->exactDeadline(), $other);

        Livewire::withQueryParams(['destination' => ['china'], 'sort' => 'closing'])->test(ScholarshipDirectory::class)
            ->assertSet('destination', ['china'])->assertSet('sort', 'closing')
            ->assertSee($in->programme->title)->assertDontSee($out->programme->title);
    }

    public function test_within_a_filter_values_are_or_and_across_filters_are_and(): void
    {
        $a = Country::factory()->destination()->create(['slug' => 'a']);
        $b = Country::factory()->destination()->create(['slug' => 'b']);
        $c = Country::factory()->destination()->create(['slug' => 'c']);
        $m = DegreeLevel::factory()->create(['slug' => 'm']);
        $p = DegreeLevel::factory()->create(['slug' => 'p']);
        $inA = $this->publishedCycle([], null, $a, $m);
        $inB = $this->publishedCycle([], null, $b, $m);
        $inCmaster = $this->publishedCycle([], null, $c, $m);
        $inAphd = $this->publishedCycle([], null, $a, $p);

        Livewire::test(ScholarshipDirectory::class)->set('destination', ['a', 'b'])->set('degree', ['m'])
            ->assertSee($inA->programme->title)->assertSee($inB->programme->title)
            ->assertDontSee($inCmaster->programme->title)->assertDontSee($inAphd->programme->title);
    }

    public function test_any_nationality_is_explicit_and_unknown_eligibility_is_not_matched(): void
    {
        $pk = Country::factory()->create(['slug' => 'pakistan']);
        $any = $this->publishedCycle(['any_nationality' => true]);
        $listed = $this->publishedCycle();
        $listed->nationalities()->attach($pk);
        $unknown = $this->publishedCycle();
        $other = $this->publishedCycle();
        $other->nationalities()->attach(Country::factory()->create());

        Livewire::test(ScholarshipDirectory::class)->set('nationality', ['pakistan'])
            ->assertSee($any->programme->title)->assertSee($listed->programme->title)
            ->assertDontSee($unknown->programme->title)->assertDontSee($other->programme->title);
    }

    public function test_closed_calls_are_hidden_by_default_and_shown_on_request_with_a_label(): void
    {
        $this->travelTo('2027-01-01');
        $open = $this->publishedCycle([], ['date_type' => 'exact', 'local_closes_at' => '2027-02-01 12:00', 'source_timezone' => 'UTC']);
        $closed = $this->publishedCycle([], ['date_type' => 'exact', 'local_closes_at' => '2027-01-02 12:00', 'source_timezone' => 'UTC']);
        $this->travelTo('2027-01-05'); // no scheduled refresh ran

        Livewire::test(ScholarshipDirectory::class)->assertSee($open->programme->title)->assertDontSee($closed->programme->title);
        Livewire::test(ScholarshipDirectory::class)->set('show_closed', true)->assertSee($closed->programme->title)->assertSee('Closed');
    }

    public function test_sorting_by_closing_time_puts_unknown_deadlines_last(): void
    {
        $unknown = $this->publishedCycle(['programme_id' => ScholarshipProgramme::factory()->create(['title' => 'ZZZ Unknown deadline'])->id], ['date_type' => 'not_announced']);
        $later = $this->publishedCycle(['programme_id' => ScholarshipProgramme::factory()->create(['title' => 'BBB Later'])->id], $this->exactDeadline('+40 days'));
        $soon = $this->publishedCycle(['programme_id' => ScholarshipProgramme::factory()->create(['title' => 'AAA Soon'])->id], $this->exactDeadline('+5 days'));

        Livewire::test(ScholarshipDirectory::class)->set('sort', 'closing')
            ->assertSeeInOrder(['AAA Soon', 'BBB Later', 'ZZZ Unknown deadline']);
    }

    public function test_keyword_search_covers_title_funder_and_subject_aliases_and_empty_results_offer_help(): void
    {
        $cycle = $this->publishedCycle();
        $cycle->programme->update(['title' => 'Quantum Futures Scholarship']);
        $subject = Subject::create(['name' => 'Physics', 'slug' => 'physics']);
        SubjectAlias::create(['subject_id' => $subject->id, 'alias' => 'astrophysics']);
        $cycle->subjects()->attach($subject);
        app(SearchIndexer::class)->reindex($cycle->fresh());

        Livewire::test(ScholarshipDirectory::class)->set('q', 'quantum')->assertSee('Quantum Futures');
        Livewire::test(ScholarshipDirectory::class)->set('q', 'astrophysics')->assertSee('Quantum Futures');
        Livewire::test(ScholarshipDirectory::class)->set('q', 'nonexistentterm')->assertSee('No scholarships match')->assertSee('Ask us');
    }

    public function test_like_wildcards_in_keywords_are_escaped(): void
    {
        $c = $this->publishedCycle();
        $c->programme->update(['title' => 'Plain Award']);
        app(SearchIndexer::class)->reindex($c->fresh());
        Livewire::test(ScholarshipDirectory::class)->set('q', '%')->assertSee('No scholarships match');
    }

    public function test_individual_filters_can_be_removed_and_all_cleared(): void
    {
        Country::factory()->destination()->create(['slug' => 'china', 'name' => 'China']);
        Livewire::test(ScholarshipDirectory::class)->set('destination', ['china'])->set('q', 'x')
            ->call('removeFilter', 'destination', 'china')->assertSet('destination', [])->assertSet('q', 'x')
            ->call('clearAll')->assertSet('q', '');
    }

    public function test_the_search_text_covers_funder_university_subject_aliases_and_is_rebuilt_by_the_command(): void
    {
        $cycle = $this->publishedCycle();
        $funder = Funder::create(['name' => 'Zephyr Foundation', 'slug' => 'zephyr']);
        $cycle->programme->update(['funder_id' => $funder->id, 'title' => 'Plain Award']);
        $uni = University::create(['name' => 'Northgate Institute', 'slug' => 'northgate']);
        $cycle->universities()->attach($uni);

        $this->artisan('sas:reindex-search')->expectsOutput('Reindexed 1 cycle(s).')->assertSuccessful();

        foreach (['zephyr', 'NORTHGATE', 'plain award'] as $term) {
            Livewire::test(ScholarshipDirectory::class)->set('q', $term)->assertSee('Plain Award');
        }
        Livewire::test(ScholarshipDirectory::class)->set('q', 'zephyr plain')->assertSee('Plain Award'); // terms are AND-ed
        Livewire::test(ScholarshipDirectory::class)->set('q', 'zephyr missingword')->assertSee('No scholarships match');
    }

    public function test_publishing_indexes_the_cycle_so_it_is_findable_immediately(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $reviewer = User::factory()->role(Role::Reviewer)->create();
        $cycle = ScholarshipCycle::factory()->create(['created_by' => $editor->id, 'summary' => 'Unique pelican wording']);
        SourceEvidence::create(['cycle_id' => $cycle->id, 'url' => 'https://example.org', 'publisher' => 'F', 'publisher_type' => 'funder', 'checked_at' => now()]);
        $wf = app(CycleWorkflow::class);
        $wf->submitForReview($cycle, $editor);
        $wf->approve($cycle->fresh(), $reviewer);
        $wf->publish($cycle->fresh(), $reviewer);

        Livewire::test(ScholarshipDirectory::class)->set('q', 'pelican')->assertSee($cycle->programme->title);
    }
}
