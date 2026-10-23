<?php

namespace Tests\Feature\Student;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Documents\Requirements;
use App\Domain\StudentWorkspace\PlanService;
use App\Domain\StudentWorkspace\StudyLanguages;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\Document;
use App\Models\DocumentKind;
use App\Models\DocumentRule;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BaselineLanguageAndUploadsTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents']);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::PDF);
    }

    private function cycleIn(string $code, array $levels = ['master', 'doctorate']): ScholarshipCycle
    {
        $cycle = ScholarshipCycle::factory()->published()->create();
        $cycle->degreeLevels()->sync(DegreeLevel::whereIn('code', $levels)->pluck('id'));
        $cycle->destinations()->sync([Country::where('code', $code)->value('id') ?? Country::factory()->create(['code' => $code, 'name' => "Country $code"])->id]);

        return $cycle->refresh();
    }

    private function rows($cycle, string $level, User $student): array
    {
        return app(Requirements::class)->forScholarship($cycle->refresh(), DegreeLevel::where('code', $level)->value('id'), $student->id)->keyBy(fn ($r) => $r['kind']->key)->all();
    }

    public function test_each_document_input_takes_several_files_and_each_can_be_removed(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->post('/student/documents', ['files' => [$this->pdf('degree-a.pdf'), $this->pdf('degree-b.pdf'), $this->pdf('degree-c.pdf')], 'type' => 'degree_certificate', 'acknowledge' => '1'])->assertRedirect();

        $docs = Document::where('user_id', $student->id)->where('type', 'degree_certificate')->get();
        $this->assertCount(3, $docs);
        $this->assertEqualsCanonicalizing(['degree-a', 'degree-b', 'degree-c'], $docs->pluck('title')->all());

        $this->post('/student/documents', ['files' => array_map(fn ($i) => $this->pdf("f$i.pdf"), range(1, 11)), 'type' => 'cv', 'acknowledge' => '1'])->assertSessionHasErrors('files');
        $this->assertSame(0, Document::where('type', 'cv')->count());

        $this->delete(route('student.documents.destroy', $docs->first()), ['back' => 1, 'confirm' => 1], ['Referer' => route('student.dashboard')])->assertRedirect(route('student.dashboard'));
        $this->assertSame(2, Document::where('type', 'degree_certificate')->count());
    }

    public function test_the_checklist_input_accepts_multiple_files_and_lists_them(): void
    {
        $cycle = $this->cycleIn('GB');
        $student = User::factory()->create();
        $plan = app(PlanService::class)->create($student, $cycle);
        $this->actingAs($student)->post('/student/documents', ['files' => [$this->pdf('bachelor.pdf'), $this->pdf('master.pdf')], 'type' => 'degree_certificate', 'acknowledge' => '1']);

        $this->get(route('student.applications.materials', $plan))->assertOk()->assertSee('bachelor.pdf')->assertSee('master.pdf')->assertSee('name="files[]"', false)->assertSee('multiple', false);
    }

    public function test_the_masters_and_phd_baseline_fills_the_gaps_the_scholarship_leaves(): void
    {
        $this->artisan('sas:import-document-rules', ['path' => base_path('data/document-rules/_baseline-masters-and-phd.json')])->assertSuccessful();
        $cycle = $this->cycleIn('DE');
        $student = User::factory()->create();
        $passport = DocumentKind::where('key', 'passport')->value('id');
        $cv = DocumentKind::where('key', 'cv')->value('id');
        $english = DocumentKind::where('key', 'english_language')->value('id');
        DocumentRule::create(['kind_id' => $passport, 'programme_id' => $cycle->programme_id, 'requirement' => 'optional', 'note' => DocumentRule::NOT_LISTED]); // official pages are silent
        DocumentRule::create(['kind_id' => $english, 'programme_id' => $cycle->programme_id, 'requirement' => 'not_needed', 'note' => 'The funder says no English test is needed.']); // official pages say so

        $master = $this->rows($cycle, 'master', $student);
        $this->assertSame('required', $master['passport']['requirement'], 'a "not listed" rule does not weaken the SAS baseline');
        $this->assertStringContainsString('1.5 years', $master['passport']['note']);
        $this->assertSame('required', $master['cv']['requirement']);
        $this->assertSame('required', $master['police_clearance']['requirement']);
        $this->assertSame('optional', $master['employment_letter']['requirement']);
        $this->assertSame('optional', $master['publications']['requirement']);
        $this->assertStringContainsString('800 to 1,000 words', $master['study_plan']['note']);
        $this->assertArrayNotHasKey('english_language', $master, 'an explicit official "not needed" still wins');

        $phd = $this->rows($cycle, 'doctorate', $student);
        $this->assertStringContainsString('1,000 to 1,500 words', $phd['study_plan']['note']);
        $this->assertSame('required', $phd['recommendation']['requirement']);

        $bachelor = app(Requirements::class)->forScholarship($cycle, DegreeLevel::where('code', 'bachelor')->value('id'), $student->id)->keyBy(fn ($r) => $r['kind']->key);
        $this->assertSame('optional', $bachelor['cv']['requirement'], 'the baseline is for master and doctorate only');
        $this->assertSame($cv, $bachelor['cv']['kind']->id);
    }

    public function test_the_study_language_is_english_or_the_official_language_of_the_host_country(): void
    {
        $de = $this->cycleIn('DE');
        $uk = $this->cycleIn('GB');
        $kz = $this->cycleIn('KZ');
        $ch = $this->cycleIn('CH');
        $languages = app(StudyLanguages::class);

        $this->assertSame(['English', 'German'], $languages->optionsFor($de));
        $this->assertSame(['English'], $languages->optionsFor($uk));
        $this->assertSame(['English', 'Kazakh', 'Russian'], $languages->optionsFor($kz));
        $this->assertSame(['English', 'German', 'French', 'Italian', 'Romansh'], $languages->optionsFor($ch));

        Country::where('code', 'DE')->update(['official_languages' => json_encode(['German', 'Sorbian'])]);
        $this->assertSame(['English', 'German', 'Sorbian'], $languages->optionsFor($de->refresh()->load('destinations')), 'staff can override a country\'s languages');
    }

    public function test_the_student_picks_the_study_language_for_each_application(): void
    {
        $de = $this->cycleIn('DE');
        $uk = $this->cycleIn('GB');
        $student = User::factory()->create();
        $planDe = app(PlanService::class)->create($student, $de);
        $planUk = app(PlanService::class)->create($student, $uk);

        $this->actingAs($student)->get(route('student.applications.show', $planDe))->assertSee('Language of study')->assertSee('German')->assertSee('English');
        $this->get(route('student.applications.show', $planUk))->assertSee('No other official language is listed', false);

        $this->put(route('student.applications.language.update', $planDe), ['study_language' => 'German'])->assertRedirect();
        $this->assertSame('German', $planDe->refresh()->study_language);
        $this->put(route('student.applications.language.update', $planDe), ['study_language' => 'Chinese'])->assertSessionHasErrors('study_language');
        $this->assertSame('German', $planDe->refresh()->study_language, 'only English or the host country\'s language is accepted');
        $this->put(route('student.applications.language.update', $planUk), ['study_language' => 'German'])->assertSessionHasErrors('study_language');

        $this->actingAs(User::factory()->create())->put(route('student.applications.language.update', $planDe), ['study_language' => 'English'])->assertForbidden();
    }

    public function test_the_details_form_no_longer_fixes_chinese_and_english_and_takes_any_other_languages(): void
    {
        $forms = app(ApplicationFormService::class);
        $fields = collect($forms->sections())->flatMap(fn ($s) => array_keys($s['fields']))->all();
        foreach (['teaching_language', 'hsk_holder', 'hsk_level', 'chinese_level'] as $gone) {
            $this->assertNotContains($gone, $fields);
        }

        $student = User::factory()->create();
        $this->actingAs($student)->put(route('student.form.update'), ['sections' => ['other_languages' => ['rows' => [['language' => 'Turkish', 'level' => 'Intermediate', 'certificate' => 'TOMER'], ['language' => 'Chinese', 'level' => 'Beginner', 'certificate' => 'HSK 2']]]]])->assertSessionHasNoErrors();
        $this->assertSame(['Turkish', 'Chinese'], array_column($forms->formFor($student)->value('other_languages'), 'language'));
        $this->get(route('student.form.show'))->assertSee('Other languages you know')->assertSee('Chinese, Turkish, German');
    }
}
