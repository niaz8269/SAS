<?php

namespace Tests\Feature\Cases;

use App\Domain\Applications\ApplicationFormPdf;
use App\Domain\Applications\ApplicationFormService;
use App\Domain\Cases\CaseService;
use App\Domain\Documents\Requirements;
use App\Domain\Identity\AccountPrivacy;
use App\Enums\Role;
use App\Filament\Resources\DocumentKinds\DocumentKindResource;
use App\Filament\Resources\DocumentKinds\Pages\EditDocumentKind;
use App\Filament\Resources\DocumentKinds\RelationManagers\RulesRelationManager;
use App\Filament\Resources\ScholarshipCycles\Pages\EditScholarshipCycle;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\DocumentRulesRelationManager;
use App\Filament\Resources\ServiceCases\Pages\EditServiceCase;
use App\Models\ApplicationForm;
use App\Models\Audit;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\Document;
use App\Models\DocumentKind;
use App\Models\DocumentRule;
use App\Models\ScholarshipCycle;
use App\Models\ServiceCase;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationFormAndDocumentRulesTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    private function staff(Role $role): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();

        return $user;
    }

    private function rows(ServiceCase $case, ?array $only = null): array
    {
        return app(Requirements::class)->forCase($case->refresh()->load('cycle'), $only)->mapWithKeys(fn ($r) => [$r['kind']->key => $r['requirement']])->all();
    }

    private function rule(string $key, string $requirement, $programmeId = null, $levelId = null): DocumentRule
    {
        return DocumentRule::create(['kind_id' => DocumentKind::where('key', $key)->value('id'), 'programme_id' => $programmeId, 'degree_level_id' => $levelId, 'requirement' => $requirement]);
    }

    public function test_the_default_document_list_comes_from_the_document_types(): void
    {
        $case = app(CaseService::class)->open(User::factory()->create(), null, null, ['title' => 'Plain']);
        $rows = $this->rows($case);

        $this->assertSame('required', $rows['passport']);
        $this->assertSame('required', $rows['photo']);
        $this->assertSame('optional', $rows['medical']);
        $this->assertArrayHasKey('csca', $rows);
    }

    public function test_the_most_specific_rule_wins_and_not_needed_hides_a_document(): void
    {
        $bachelor = DegreeLevel::factory()->create(['name' => 'Bachelor']);
        $master = DegreeLevel::factory()->create(['name' => 'Master']);
        $cycle = ScholarshipCycle::factory()->published()->create();
        $other = ScholarshipCycle::factory()->published()->create();
        $case = app(CaseService::class)->open(User::factory()->create(), $cycle, null, []);
        app(CaseService::class)->setDegreeLevel($case, $bachelor->id);

        $this->rule('csca', 'required', null, $bachelor->id);                          // level only
        $this->rule('study_plan', 'required', $cycle->programme_id);                   // scholarship only
        $this->rule('recommendation', 'required', $cycle->programme_id);              // scholarship...
        $this->rule('recommendation', 'optional', $cycle->programme_id, $bachelor->id); // ...beaten by scholarship + level
        $this->rule('medical', 'required', $other->programme_id);                      // a different scholarship
        $this->rule('csca', 'not_needed', null, $master->id);                          // a different level
        $this->rule('cv', 'not_needed');                                               // everyone

        $rows = $this->rows($case);
        $this->assertSame('required', $rows['csca']);
        $this->assertSame('required', $rows['study_plan']);
        $this->assertSame('optional', $rows['recommendation']);
        $this->assertSame('optional', $rows['medical'], 'a rule for another scholarship does not apply');
        $this->assertArrayNotHasKey('cv', $rows, 'not needed documents are hidden');

        app(CaseService::class)->setDegreeLevel($case, $master->id);
        $this->assertArrayNotHasKey('csca', $this->rows($case));
        $this->assertSame('required', $this->rows($case)['recommendation'], 'the scholarship-only rule applies again');
    }

    public function test_switched_off_document_types_are_not_listed(): void
    {
        DocumentKind::where('key', 'portfolio')->update(['active' => false]);
        $case = app(CaseService::class)->open(User::factory()->create(), null, null, ['title' => 'X']);

        $this->assertArrayNotHasKey('portfolio', $this->rows($case));
    }

    public function test_progress_counts_mandatory_documents_and_a_rejected_scan_is_missing(): void
    {
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents']);
        $this->withoutMiddleware(ThrottleRequests::class);
        $student = User::factory()->create();
        $case = app(CaseService::class)->open($student, null, null, ['title' => 'Docs']);

        $before = app(Requirements::class)->progress(app(Requirements::class)->forCase($case));
        $this->assertSame(['required' => 4, 'ready' => 0], ['required' => $before['required'], 'ready' => $before['ready']]);

        $this->actingAs($student)->post('/student/documents', ['file' => UploadedFile::fake()->createWithContent('p.pdf', self::PDF), 'type' => 'passport', 'title' => 'Passport', 'acknowledge' => '1'])->assertRedirect();
        $after = app(Requirements::class)->progress(app(Requirements::class)->forCase($case));
        $this->assertSame(1, $after['ready']);
        $this->assertNotContains('Passport (home page)', $after['missing']);
    }

    public function test_staff_checklist_counts_only_documents_the_student_shared(): void
    {
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents']);
        $student = User::factory()->create();
        $case = app(CaseService::class)->open($student, null, null, ['title' => 'Shared only']);
        $this->actingAs($student)->post('/student/documents', ['file' => UploadedFile::fake()->createWithContent('p.pdf', self::PDF), 'type' => 'passport', 'title' => 'Passport', 'acknowledge' => '1']);
        $doc = Document::firstOrFail();

        $unshared = app(Requirements::class)->forCase($case, []);
        $this->assertSame('missing', $unshared->firstWhere(fn ($r) => $r['kind']->key === 'passport')['state']);

        app(CaseService::class)->syncShares($case, $student, [$doc->id], 'consent');
        $shared = app(Requirements::class)->forCase($case, $case->shares()->pluck('document_id')->all());
        $this->assertSame('ready', $shared->firstWhere(fn ($r) => $r['kind']->key === 'passport')['state']);
    }

    public function test_form_answers_are_validated_saved_together_and_encrypted(): void
    {
        $student = User::factory()->create(['name' => 'Amina Khan']);
        $china = Country::factory()->create(['name' => 'Pakistan']);
        $this->actingAs($student)->get(route('student.form.show'))->assertOk()->assertSee('Personal information')->assertSee('Amina');

        $this->put(route('student.form.update'), ['sections' => ['personal' => ['surname' => 'Khan', 'given_name' => 'Amina', 'nationality' => 'Atlantis', 'passport_no' => 'AB1234567']]])->assertSessionHasErrors('sections.personal.nationality');
        $this->put(route('student.form.update'), ['sections' => ['personal' => ['surname' => 'Khan', 'given_name' => 'Amina', 'nationality' => 'Pakistan', 'passport_no' => 'AB1234567', 'gender' => 'Female'], 'contact' => ['phone' => '+923001234567']]])->assertSessionHasNoErrors();
        $this->put(route('student.form.update'), ['sections' => ['contact' => ['email' => 'not-an-email']]])->assertSessionHasErrors('sections.contact.email');

        $form = ApplicationForm::where('user_id', $student->id)->firstOrFail();
        $this->assertSame('AB1234567', $form->value('passport_no'));
        $this->assertSame('Pakistan', $form->value('nationality'));
        $this->assertSame('+923001234567', $form->value('phone'));
        $this->assertStringNotContainsString('AB1234567', (string) DB::table('application_forms')->where('user_id', $student->id)->value('data'), 'stored encrypted');

        $this->put(route('student.form.update'), ['sections' => ['personal' => ['surname' => 'Khan', 'given_name' => 'Amina', 'nationality' => 'Pakistan']]])->assertSessionHasNoErrors();
        $this->assertNull($form->refresh()->value('passport_no'), 'a blank answer clears it');
        $this->assertSame($china->name, $form->value('nationality'));
        $this->assertSame('+923001234567', $form->value('phone'), 'a section that was not sent is left alone');
    }

    public function test_repeating_sections_keep_filled_rows_and_cap_the_count(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->put(route('student.form.update'), ['sections' => ['employment' => ['rows' => [['employer' => 'Acme', 'duration' => '2021 to 2023'], ['employer' => ''], ['employer' => 'Beta']]]]])->assertSessionHasNoErrors();
        $this->assertSame(['Acme', 'Beta'], array_column(ApplicationForm::firstOrFail()->value('employment'), 'employer'));

        $this->put(route('student.form.update'), ['sections' => ['employment' => ['rows' => [['employer' => 'a'], ['employer' => 'b'], ['employer' => 'c'], ['employer' => 'd']]]]])->assertSessionHasErrors('sections.employment.rows');
    }

    public function test_completeness_needs_the_required_answers_and_the_declaration(): void
    {
        $student = User::factory()->create();
        $service = app(ApplicationFormService::class);
        $form = $service->formFor($student);
        $this->assertContains('Passport number', $service->missing($form));
        $this->assertContains('Declaration', $service->missing($form));
        $this->assertLessThan(100, $service->percent($form));

        $answers = [];
        foreach ($service->sections() as $key => $section) {
            foreach ($section['fields'] as $name => $def) {
                if ($def['required'] ?? false) {
                    $answers[$name] = match ($def[1]) {
                        'date' => '2000-01-01', 'email' => 'a@example.test', 'select' => $def['options'][0], default => 'x'
                    };
                }
            }
        }
        $this->assertContains('Other education certificates, entry 1: Institute name', $service->missing($form->forceFill(['data' => $answers])), 'one education record is not enough');
        $answers['other_education'] = [['level' => 'Senior high school', 'institute' => 'Another School', 'from' => '2015-09-01', 'to' => '2019-06-30']]; // SAS needs two education records
        $form->forceFill(['data' => $answers])->save();
        $this->assertSame(['Declaration'], $service->missing($form));
        $this->actingAs($student)->put(route('student.form.update'), ['declared' => '1'])->assertRedirect();
        $this->assertSame([], $service->missing($form->refresh()));
        $this->assertSame(100, $service->percent($form));
    }

    public function test_education_needs_two_records_with_full_dates_and_the_end_cannot_precede_the_start(): void
    {
        $this->seed(TaxonomySeeder::class); // the country list
        $student = User::factory()->create();
        $service = app(ApplicationFormService::class);
        $this->actingAs($student);
        $save = fn (array $education, array $other = []) => $this->put(route('student.form.update'), ['sections' => ['education' => $education, 'other_education' => ['rows' => $other]]]);
        $record = ['edu_level' => 'Bachelor', 'edu_country' => 'Pakistan', 'edu_institute' => 'University of the Punjab', 'edu_from' => '2019-09-15', 'edu_to' => '2023-06-30'];

        // The first record alone is not enough: the second one is asked for by name.
        $save($record)->assertRedirect()->assertSessionHasNoErrors();
        $missing = $service->missing($service->formFor($student)->refresh());
        $this->assertContains('Other education certificates, entry 1: Education level', $missing);
        $this->assertContains('Other education certificates, entry 1: Studied from (day, month, year)', $missing);
        $this->assertNotContains('Studied from (day, month, year)', $missing, 'the first record is complete');

        // Dates are whole dates, and the end must not come before the start.
        $save(['edu_from' => '2023-06-30', 'edu_to' => '2019-09-15'] + $record)->assertSessionHasErrors('sections.education.edu_to');
        $save(['edu_from' => now()->addYear()->toDateString(), 'edu_to' => now()->addYears(3)->toDateString()] + $record)->assertSessionHasErrors('sections.education.edu_from');
        $save(['edu_from' => '1900-01-01'] + $record)->assertSessionHasErrors('sections.education.edu_from');
        $save(['edu_from' => 'September 2019'] + $record)->assertSessionHasErrors('sections.education.edu_from');
        $save($record, [['level' => 'Senior high school', 'institute' => 'City School', 'from' => '2018-06-01', 'to' => '2015-01-01']])->assertSessionHasErrors('sections.other_education.rows.0.to');

        // Two good records: complete. A third that is started must be filled in properly too.
        $save($record, [['level' => 'Senior high school', 'institute' => 'City School', 'from' => '2015-09-01', 'to' => '2017-06-30']])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotContains('Other education certificates, entry 1: Institute name', $service->missing($service->formFor($student)->refresh()));
        $save($record, [['level' => 'Senior high school', 'institute' => 'City School', 'from' => '2015-09-01', 'to' => '2017-06-30'], ['institute' => 'Half done']])->assertRedirect();
        $missing = $service->missing($service->formFor($student)->refresh());
        $this->assertContains('Other education certificates, entry 2: Education level', $missing);
        $this->assertNotContains('Other education certificates, entry 1: Education level', $missing);
        $progress = $service->sectionProgress($service->formFor($student)->refresh());
        $this->assertSame(['required' => 8, 'answered' => 5], $progress['other_education'], 'four needed in each of the two entries; entry 1 is complete and entry 2 has only its institute');

        // The page shows the note and the date boxes.
        $this->get(route('student.form.show'))->assertOk()->assertSee('SAS needs two')->assertSee('Studied from (day, month, year)')->assertSee('type="date"', false);
    }

    public function test_the_sas_form_pdf_writes_the_study_dates_in_full(): void
    {
        $pdf = new \ReflectionMethod(ApplicationFormPdf::class, 'period');
        $pdf->setAccessible(true);
        $instance = app(ApplicationFormPdf::class);
        $data = ['edu_from' => '2019-09-15', 'edu_to' => '2023-06-30', 'other_education' => [['from' => '2015-09-01', 'to' => '2017-06-30'], ['years' => '2010 to 2012']]];

        $this->assertSame('15 Sep 2019 to 30 Jun 2023', $pdf->invoke($instance, $data, 'edu'));
        $this->assertSame('1 Sep 2015 to 30 Jun 2017', $pdf->invoke($instance, $data, 'other_education.0'));
        $this->assertSame('2010 to 2012', $pdf->invoke($instance, $data, 'other_education.1'), 'an older record with only free text still shows');
        $this->assertNull($pdf->invoke($instance, [], 'edu'));
    }

    public function test_the_case_page_links_to_the_documents_and_the_level_follows_the_students_application(): void
    {
        $level = DegreeLevel::factory()->create(['name' => 'Master']);
        $cycle = ScholarshipCycle::factory()->published()->create();
        $student = User::factory()->create();
        $case = app(CaseService::class)->open($student, $cycle, null, []);
        $plan = $student->applicationPlans()->where('cycle_id', $cycle->id)->firstOrFail(); // opening a case gives the student their own application page
        $this->rule('csca', 'required', $cycle->programme_id, $level->id);

        $this->actingAs($student)->get(route('student.cases.show', $case))->assertOk()
            ->assertSee('Your documents')->assertSee(route('student.applications.materials', $plan))->assertSee('Let your counsellor see your details');

        $this->put(route('student.applications.level.update', $plan), ['degree_level_id' => $level->id])->assertRedirect();
        $this->assertSame($level->id, $case->refresh()->degree_level_id, 'the case follows the level the student chose');
        $this->get(route('student.applications.materials', $plan))->assertSee('CSCA score report');
        $this->assertSame('required', $this->rows($case)['csca']);

        $this->put(route('student.cases.form.update', $case), ['share' => '1'])->assertRedirect();
        $this->assertNotNull($case->refresh()->form_shared_at);
        $this->assertStringContainsString('may read my application form', $case->form_consent_text);
        $this->put(route('student.cases.form.update', $case), [])->assertRedirect();
        $this->assertNull($case->refresh()->form_shared_at);

        $other = User::factory()->create();
        $this->actingAs($other)->put(route('student.applications.level.update', $plan), ['degree_level_id' => $level->id])->assertForbidden();
        $this->put(route('student.cases.form.update', $case), ['share' => '1'])->assertForbidden();
    }

    public function test_staff_read_the_form_only_after_the_student_shares_it_and_only_for_their_cases(): void
    {
        $student = User::factory()->create(['name' => 'Amina Khan']);
        $form = app(ApplicationFormService::class)->formFor($student);
        $form->forceFill(['data' => array_merge($form->data, ['passport_no' => 'ZZ9876543'])])->save();
        $advisor = $this->staff(Role::Advisor);
        $mine = app(CaseService::class)->open($student, null, $advisor, ['title' => 'Mine']);
        $notMine = app(CaseService::class)->open($student, null, null, ['title' => 'Not mine']);

        $this->actingAs($advisor);
        Livewire::test(EditServiceCase::class, ['record' => $mine->getRouteKey()])->assertActionHidden('applicationForm');

        app(CaseService::class)->shareForm($mine, $student, true, 'consent');
        Livewire::test(EditServiceCase::class, ['record' => $mine->getRouteKey()])->assertActionVisible('applicationForm')->mountAction('applicationForm');
        $this->assertTrue(Audit::where('action', 'case.form_viewed')->where('auditable_id', $mine->id)->exists());
        $this->assertStringContainsString('ZZ9876543', view('filament.case-application-form', ['case' => $mine->refresh(), 'form' => $mine->sharedForm()])->render());

        $this->get(route('filament.admin.resources.service-cases.edit', $notMine))->assertNotFound(); // advisors cannot even see cases that are not theirs
    }

    public function test_only_staff_with_document_permission_manage_document_types_and_rules(): void
    {
        $this->actingAs($this->staff(Role::Administrator));
        $this->assertTrue(DocumentKindResource::canViewAny());
        auth()->logout();
        foreach ([Role::Advisor] as $role) {
            $this->actingAs($this->staff($role));
            $this->assertFalse(DocumentKindResource::canViewAny(), $role->value);
            auth()->logout();
        }
    }

    public function test_a_manager_sets_rules_per_document_and_per_scholarship_and_duplicates_are_refused(): void
    {
        $manager = $this->staff(Role::Administrator);
        $this->actingAs($manager);
        $level = DegreeLevel::factory()->create(['name' => 'Bachelor']);
        $cycle = ScholarshipCycle::factory()->published()->create();
        $csca = DocumentKind::where('key', 'csca')->firstOrFail();

        Livewire::test(RulesRelationManager::class, ['ownerRecord' => $csca, 'pageClass' => EditDocumentKind::class])
            ->callTableAction('create', data: ['degree_level_id' => $level->id, 'requirement' => 'required', 'note' => 'Bachelor applicants only'])->assertHasNoTableActionErrors();
        $this->assertDatabaseHas('document_rules', ['kind_id' => $csca->id, 'programme_id' => null, 'degree_level_id' => $level->id, 'requirement' => 'required']);

        Livewire::test(RulesRelationManager::class, ['ownerRecord' => $csca, 'pageClass' => EditDocumentKind::class])
            ->callTableAction('create', data: ['degree_level_id' => $level->id, 'requirement' => 'optional'])->assertHasTableActionErrors(['requirement']);
        $this->assertSame(1, DocumentRule::where('kind_id', $csca->id)->count());

        Livewire::test(DocumentRulesRelationManager::class, ['ownerRecord' => $cycle, 'pageClass' => EditScholarshipCycle::class])
            ->callTableAction('create', data: ['kind_id' => DocumentKind::where('key', 'medical')->value('id'), 'requirement' => 'required'])->assertHasNoTableActionErrors();
        $this->assertDatabaseHas('document_rules', ['programme_id' => $cycle->programme_id, 'degree_level_id' => null, 'requirement' => 'required']);
    }

    public function test_an_editor_cannot_see_the_document_rules_on_a_scholarship(): void
    {
        $this->actingAs($this->staff(Role::Advisor));
        $this->assertFalse(DocumentRulesRelationManager::canViewForRecord(ScholarshipCycle::factory()->published()->create(), EditScholarshipCycle::class));
    }

    public function test_uploads_only_accept_active_document_types(): void
    {
        Storage::fake('private_documents');
        config(['sas.uploads.disk' => 'private_documents']);
        $this->withoutMiddleware(ThrottleRequests::class);
        $student = User::factory()->create();
        DocumentKind::where('key', 'publications')->update(['active' => false]);
        $file = fn () => UploadedFile::fake()->createWithContent('p.pdf', self::PDF);

        $this->actingAs($student)->post('/student/documents', ['file' => $file(), 'type' => 'publications', 'acknowledge' => '1'])->assertSessionHasErrors('type');
        $this->post('/student/documents', ['file' => $file(), 'type' => 'csca', 'acknowledge' => '1'])->assertSessionHasNoErrors();
    }

    public function test_the_data_export_includes_the_application_form(): void
    {
        $student = User::factory()->create();
        app(ApplicationFormService::class)->save($student, 'personal', ['surname' => 'Khan', 'given_name' => 'Amina']);

        $export = app(AccountPrivacy::class)->export($student);
        $this->assertSame('Khan', $export['application_form']['surname']);
    }
}
