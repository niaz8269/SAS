<?php

namespace Database\Seeders;

use App\Domain\Editorial\CycleWorkflow;
use App\Domain\Scholarship\ChangeDeadline;
use App\Enums\Role;
use App\Models\Article;
use App\Models\Country;
use App\Models\CycleBenefit;
use App\Models\CycleCriterion;
use App\Models\CycleRequirement;
use App\Models\DegreeLevel;
use App\Models\Funder;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\ScholarshipRoute;
use App\Models\Service;
use App\Models\SourceEvidence;
use App\Models\Subject;
use App\Models\University;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * DEMONSTRATION DATA ONLY. Every record is fictional and prefixed "[DEMO]". It is never real scholarship data and the
 * seeder refuses to run in production (SCHEMA-07). Records go through the real editorial workflow.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder refuses to run in production.');

            return;
        }
        $this->call(TaxonomySeeder::class);
        $this->call(ServiceCatalogueSeeder::class);

        $password = 'demo-password-123'; // local demonstration accounts only
        $users = [];
        foreach ([Role::Editor, Role::Reviewer, Role::Administrator, Role::ServiceManager, Role::Advisor] as $role) {
            $u = User::firstOrCreate(['email' => 'demo-'.$role->value.'@example.test'], ['name' => '[DEMO] '.$role->label(), 'password' => $password]);
            $u->forceFill(['email_verified_at' => now()])->save();
            $u->userRoles()->firstOrCreate(['role' => $role->value]);
            $users[$role->value] = $u;
        }
        $student = User::firstOrCreate(['email' => 'demo-student@example.test'], ['name' => '[DEMO] Student', 'password' => $password]);
        $student->forceFill(['email_verified_at' => now()])->save();

        Service::query()->update(['published' => true]);
        Service::whereIn('slug', ['scholarship-advisory', 'writing-and-applications', 'visa-assistance'])->update(['featured' => true]);

        $dest = ['CN' => 'China', 'DE' => 'Germany', 'GB' => 'United Kingdom', 'TR' => 'Türkiye'];
        foreach ($dest as $code => $name) {
            Country::where('code', $code)->update([
                'is_destination' => true, 'guidance_published' => true, 'featured' => true,
                'summary' => '[DEMO] Placeholder destination summary for '.$name.'. Replace with reviewed guidance.',
                'study_guidance' => '<p>[DEMO] This is placeholder text. Real destination guidance must be written from official sources and reviewed.</p>',
                'guidance_reviewed_at' => now()->toDateString(),
                'guidance_sources' => [['label' => 'Example official source', 'url' => 'https://example.org/'.Str::slug($name)]],
                'cost_notes' => [['label' => 'Demo cost line', 'amount_text' => 'Not real', 'currency' => '', 'updated_on' => now()->toDateString()]],
            ]);
        }

        $funder = Funder::firstOrCreate(['slug' => 'demo-example-foundation'], ['name' => '[DEMO] Example Foundation', 'type' => 'foundation', 'website' => 'https://example.org']);
        $uni = University::firstOrCreate(['slug' => 'demo-example-university'], ['name' => '[DEMO] Example University', 'country_id' => Country::where('code', 'DE')->value('id'), 'website' => 'https://example.edu']);
        $master = DegreeLevel::where('code', 'master')->first();
        $phd = DegreeLevel::where('code', 'doctorate')->first();
        $cs = Subject::where('slug', 'computer-science')->first();
        $eng = Subject::where('slug', 'engineering')->first();
        $pk = Country::where('code', 'PK')->first();

        $editor = $users['editor'];
        $reviewer = $users['reviewer'];
        $wf = app(CycleWorkflow::class);
        $deadline = app(ChangeDeadline::class);
        $year = now()->year + 1;

        $defs = [
            ['name' => 'Exact Deadline Award', 'dest' => 'DE', 'deg' => [$master], 'subj' => [$cs], 'funding' => 'partial', 'featured' => true,
                'deadline' => ['date_type' => 'exact', 'local_closes_at' => now()->addDays(21)->format('Y-m-d').' 17:00', 'source_timezone' => 'Europe/Berlin', 'source_text' => '17:00 CET']],
            ['name' => 'Date Only Award', 'dest' => 'CN', 'deg' => [$master], 'subj' => [$eng], 'funding' => 'full', 'verified_full' => true, 'featured' => true,
                'deadline' => ['date_type' => 'date_only', 'closes_on' => now()->addDays(12)->toDateString(), 'source_text' => 'by '.now()->addDays(12)->format('j F')]],
            ['name' => 'Rolling Admissions Award', 'dest' => 'GB', 'deg' => [$phd], 'subj' => [$eng, $cs], 'funding' => 'tuition_only', 'featured' => true,
                'deadline' => ['date_type' => 'rolling', 'source_text' => 'Applications accepted until funds are allocated']],
            ['name' => 'Unconfirmed Deadline Award', 'dest' => 'TR', 'deg' => [$master, $phd], 'subj' => [], 'funding' => 'unknown',
                'deadline' => ['date_type' => 'not_announced']],
            ['name' => 'Full Funding Unverified Award', 'dest' => 'CN', 'deg' => [$master], 'subj' => [$cs], 'funding' => 'full', 'verified_full' => false,
                'deadline' => ['date_type' => 'exact', 'local_closes_at' => now()->addDays(60)->format('Y-m-d').' 12:00', 'source_timezone' => 'Asia/Shanghai']],
            ['name' => 'Route Dependent Award', 'dest' => 'DE', 'deg' => [$master], 'subj' => [], 'funding' => 'partial',
                'deadline' => ['date_type' => 'route_dependent']],
        ];

        foreach ($defs as $i => $d) {
            $programme = ScholarshipProgramme::firstOrCreate(['slug' => 'demo-'.Str::slug($d['name'])], [
                'title' => '[DEMO] '.$d['name'], 'funder_id' => $funder->id, 'host_country_id' => Country::where('code', $d['dest'])->value('id'),
                'summary' => 'Fictional demonstration scholarship used to exercise the platform. Not a real award.', 'created_by' => $editor->id,
            ]);
            if (ScholarshipCycle::where('programme_id', $programme->id)->where('cycle_key', (string) $year)->exists()) {
                continue;
            }
            $cycle = ScholarshipCycle::create([
                'programme_id' => $programme->id, 'cycle_key' => (string) $year, 'slug' => (string) $year, 'intake_year' => $year, 'intake_term' => 'Autumn',
                'external_key' => 'demo-'.($i + 1), 'funding_type' => $d['funding'], 'fully_funded_verified' => $d['verified_full'] ?? false,
                'funding_summary' => '[DEMO] Illustrative funding summary.', 'teaching_language' => 'English', 'delivery_mode' => 'on_campus',
                'any_nationality' => $i % 2 === 0, 'summary' => '[DEMO] Fictional scholarship call for demonstration purposes only.',
                'featured' => $d['featured'] ?? false, 'created_by' => $editor->id,
            ]);
            $cycle->destinations()->attach(Country::where('code', $d['dest'])->value('id'));
            $cycle->degreeLevels()->attach(collect($d['deg'])->pluck('id'));
            $cycle->subjects()->attach(collect($d['subj'])->pluck('id'));
            $cycle->universities()->attach($uni->id);
            if (! $cycle->any_nationality) {
                $cycle->nationalities()->attach($pk->id);
            }
            CycleBenefit::create(['cycle_id' => $cycle->id, 'type' => 'tuition', 'coverage' => $d['funding'] === 'unknown' ? 'unknown' : 'covered', 'limit_text' => '[DEMO]']);
            CycleRequirement::create(['cycle_id' => $cycle->id, 'document_type' => 'transcript', 'text' => 'Academic transcripts', 'sort' => 1]);
            CycleRequirement::create(['cycle_id' => $cycle->id, 'document_type' => 'statement', 'text' => 'Statement of purpose', 'sort' => 2]);
            CycleRequirement::create(['cycle_id' => $cycle->id, 'document_type' => 'language_certificate', 'text' => 'Evidence of English language proficiency', 'sort' => 3]);
            CycleCriterion::create(['cycle_id' => $cycle->id, 'type' => 'language', 'operator' => '>=', 'value' => '6.0', 'unit' => 'IELTS', 'evidence' => '[DEMO]']);
            ScholarshipRoute::create(['cycle_id' => $cycle->id, 'route_type' => 'funder_portal', 'name' => 'Funder portal', 'application_url' => 'https://example.org/apply/'.($i + 1)]);
            SourceEvidence::create(['cycle_id' => $cycle->id, 'url' => 'https://example.org/call/'.($i + 1), 'publisher' => '[DEMO] Example Foundation',
                'publisher_type' => 'funder', 'checked_at' => now(), 'note' => 'Fictional source for demonstration.', 'created_by' => $editor->id]);

            $deadline($cycle, $d['deadline'], $editor, 'Demo seed');
            $wf->submitForReview($cycle, $editor);
            $wf->approve($cycle->fresh(), $reviewer);
            $wf->publish($cycle->fresh(), $reviewer);
        }

        Article::firstOrCreate(['slug' => 'demo-how-to-read-a-scholarship-call'], [
            'title' => '[DEMO] How to read a scholarship call', 'category' => 'guide', 'author_id' => $editor->id, 'status' => 'published', 'published_at' => now()->subDay(),
            'excerpt' => 'Placeholder guide used to demonstrate the resources section.',
            'body' => '<h2>Demo</h2><p>This is placeholder text. Real guides must be written and reviewed by editors.</p>',
        ]);
    }
}
