<?php

namespace Tests\Feature\Editorial;

use App\Enums\Role;
use App\Filament\Resources\UniversityScholarships\Pages\ManageUniversityScholarships;
use App\Models\Country;
use App\Models\University;
use App\Models\UniversityFeeStructure;
use App\Models\UniversityScholarship;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UniversityMedicalDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china']);
    }

    private function run_(array $entries, array $options = []): int
    {
        $path = tempnam(sys_get_temp_dir(), 'uni').'.json';
        file_put_contents($path, json_encode($entries));

        return $this->artisan('sas:import-university-details', ['files' => [$path]] + $options)->run();
    }

    private function entry(string $name = 'Example Medical University', array $over = []): array
    {
        return array_replace_recursive([
            'university' => ['name' => $name, 'name_zh' => '示例医科大学', 'country_code' => 'CN', 'province' => 'Jiangsu', 'city' => 'Xuzhou', 'website' => 'https://www.example.edu.cn'],
            'profile' => ['established_year' => 1958, 'affiliated_hospitals' => [['name' => 'First Affiliated Hospital', 'note' => 'Grade A']],
                'recognitions' => [['body' => 'WHO (WDOMS)', 'status' => 'verified', 'source_url' => 'https://search.wdoms.org/example'], ['body' => 'ECFMG', 'status' => 'verified']]],
            'fee_structures' => [[
                'programme_name' => 'Clinical Medicine (MBBS)', 'level' => 'bachelor', 'duration_text' => '5 + 1 years', 'duration_years' => 6, 'teaching_language' => 'English', 'intake_label' => 'September 2026',
                'currency' => 'CNY', 'tuition_amount' => 30000, 'hostel_amount' => 5000, 'application_fee_amount' => 1000,
                'other_fees' => [['label' => 'Insurance', 'amount' => 800, 'when' => 'every_year'], ['label' => 'Registration', 'amount' => 400, 'when' => 'first_year'],
                    ['label' => 'Hostel deposit', 'amount' => 1000, 'when' => 'first_year', 'refundable' => true], ['label' => 'Optional cover', 'amount' => 100, 'optional' => true]],
                'source_type' => 'university', 'source_label' => 'Tuition page', 'source_url' => 'https://www.example.edu.cn/fees', 'source_date' => '2026-10-01', 'published' => true,
            ]],
            'scholarships' => [['name' => 'University scholarship', 'provider' => 'university', 'coverage' => 'Half tuition', 'source_url' => 'https://www.example.edu.cn/scholarships', 'source_label' => 'Scholarship page', 'published' => true]],
        ], $over);
    }

    public function test_fees_are_added_up_from_the_universitys_own_charges_only(): void
    {
        $this->run_([$this->entry()]);
        $fs = UniversityFeeStructure::firstOrFail();

        $totals = $fs->totals();

        $this->assertSame(30000 + 5000 + 800 + 400, $totals['first'], 'refundable deposits and optional items are not added');
        $this->assertSame(30000 + 5000 + 800, $totals['later'], 'a first-year-only fee is not repeated');
        $this->assertFalse($totals['from']);
        $this->assertSame(1000, $fs->application_fee_amount);
    }

    public function test_a_per_semester_tuition_counts_twice_and_a_range_marks_the_total_as_a_minimum(): void
    {
        $fs = new UniversityFeeStructure(['currency' => 'USD', 'tuition_amount' => 1500, 'tuition_period' => 'semester', 'other_fees' => [['label' => 'Residence card', 'amount' => 60, 'amount_max' => 100, 'when' => 'every_year']]]);

        $this->assertSame(['first' => 3060, 'later' => 3060, 'from' => true], $fs->totals());
        $this->assertSame(['first' => null, 'later' => null, 'from' => false], (new UniversityFeeStructure(['tuition_amount' => null]))->totals());
    }

    public function test_a_university_is_listed_only_with_a_published_scholarship_and_published_fees(): void
    {
        $this->run_([$this->entry('With Scholarship University'), $this->entry('Without Scholarship University', ['scholarships' => []])]);

        $this->get(route('universities.show', 'with-scholarship-university'))->assertOk()->assertSee('Studying medicine at With Scholarship University')->assertSee('University scholarship')
            ->assertSee('CNY 36,200', false)->assertSee('Source: Tuition page');
        $this->get(route('universities.show', 'without-scholarship-university'))->assertNotFound();
        $this->get(route('universities.index'))->assertOk()->assertSee('With Scholarship University')->assertDontSee('Without Scholarship University');
    }

    public function test_hiding_the_scholarship_or_the_fees_hides_the_university(): void
    {
        $this->run_([$this->entry()]);
        $u = University::where('slug', 'example-medical-university')->firstOrFail();
        $this->get(route('universities.show', $u))->assertOk();

        UniversityScholarship::where('university_id', $u->id)->update(['published' => false]);
        $this->get(route('universities.show', $u))->assertNotFound();

        UniversityScholarship::where('university_id', $u->id)->update(['published' => true]);
        UniversityFeeStructure::where('university_id', $u->id)->update(['published' => false]);
        $this->get(route('universities.show', $u))->assertNotFound();
    }

    public function test_imports_arrive_hidden_unless_the_row_says_published_and_a_scholarship_needs_a_page_or_a_fee_sheet(): void
    {
        $hidden = $this->entry('Hidden University', ['fee_structures' => [['published' => false]], 'scholarships' => [['published' => true, 'source_url' => null]]]);

        $this->run_([$hidden]);

        $this->assertFalse((bool) UniversityFeeStructure::firstOrFail()->published);
        $this->assertFalse((bool) UniversityScholarship::firstOrFail()->published, 'a scholarship without an official page is never shown');
        $this->get(route('universities.show', 'hidden-university'))->assertNotFound();
    }

    public function test_a_fee_sheet_given_to_sas_counts_as_a_source_and_says_so(): void
    {
        $this->run_([$this->entry('Sheet University', [
            'fee_structures' => [['source_type' => 'supplied', 'source_url' => null, 'source_label' => 'Fee sheet given to SAS']],
            'scholarships' => [['source_type' => 'supplied', 'source_url' => null, 'name' => 'Sheet scholarship']],
        ])]);

        $this->get(route('universities.show', 'sheet-university'))->assertOk()->assertSee('Fee sheet given to SAS')->assertSee("From the university's fee sheet given to SAS.", false);
    }

    public function test_a_recognition_is_only_checked_when_it_has_a_link_and_the_page_says_which(): void
    {
        $this->run_([$this->entry()]);

        $recognitions = collect(University::firstOrFail()->profile['recognitions'])->keyBy('body');
        $this->assertSame('verified', $recognitions['WHO (WDOMS)']['status']);
        $this->assertSame('claimed', $recognitions['ECFMG']['status'], 'no link, so it is only a statement');
        $this->get(route('universities.show', 'example-medical-university'))->assertSee('checked by SAS')->assertSee('stated by the university, not checked by SAS');
    }

    public function test_a_school_named_in_brackets_is_the_same_university_and_official_fees_replace_a_fee_sheet(): void
    {
        $this->run_([$this->entry('Example Medical University', ['fee_structures' => [['source_type' => 'supplied', 'source_url' => null, 'tuition_amount' => 20000]]])]);
        $this->assertSame('supplied', UniversityFeeStructure::firstOrFail()->source_type);

        $this->run_([$this->entry('Example Medical University (School of Medicine)', ['fee_structures' => [['tuition_amount' => 45000]], 'scholarships' => [['name' => 'Official scholarship']]])]);

        $this->assertSame(1, University::count(), 'no second university for the school in brackets');
        $this->assertSame(['university'], UniversityFeeStructure::pluck('source_type')->all(), 'the university\'s own figures replace the sheet');
        $this->assertSame(45000, UniversityFeeStructure::firstOrFail()->tuition_amount);
    }

    public function test_an_alternative_to_another_fee_is_never_added_into_the_total(): void
    {
        $this->run_([$this->entry('Alt University', ['fee_structures' => [['other_fees' => [['label' => 'Double-room hostel (full price, instead of triple room)', 'amount' => 15000, 'when' => 'every_year']]]]])]);

        $fs = UniversityFeeStructure::firstOrFail();

        $this->assertSame(30000 + 5000 + 400, $fs->totals()['first'], 'the alternative room price is not added (the entry replaces the first listed fee, insurance)');
    }

    public function test_a_second_import_changes_nothing_and_never_overwrites_what_is_already_there(): void
    {
        $this->run_([$this->entry()]);
        $u = University::firstOrFail();
        $u->update(['city' => 'Checked City']);

        $this->run_([$this->entry('Example Medical University', ['university' => ['city' => 'Other City', 'province' => 'Other Province']])]);

        $this->assertSame(1, University::count());
        $this->assertSame(1, UniversityFeeStructure::count());
        $this->assertSame(1, UniversityScholarship::count());
        $this->assertSame('Checked City', $u->refresh()->city);
    }

    public function test_staff_publish_scholarships_in_bulk_but_only_those_with_a_source(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->role(Role::SuperAdmin)->create());
        $this->run_([$this->entry('Bulk University', ['scholarships' => [['published' => false], ['name' => 'No page', 'published' => false, 'source_url' => null]]])]);
        $all = UniversityScholarship::all();

        Livewire::test(ManageUniversityScholarships::class)->callTableBulkAction('show', $all);

        $this->assertSame(['University scholarship'], UniversityScholarship::where('published', true)->pluck('name')->all());
    }

    public function test_the_public_page_for_a_chinese_catalogue_university_is_not_changed(): void
    {
        $u = new University;
        $u->forceFill(['name' => 'Catalogue University', 'slug' => 'catalogue-university', 'csc_key' => 'K1', 'published' => true])->save();

        $this->get(route('universities.show', $u))->assertOk()->assertDontSee('Studying medicine at');
    }
}
