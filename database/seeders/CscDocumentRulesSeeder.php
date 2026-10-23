<?php

namespace Database\Seeders;

use App\Models\DegreeLevel;
use App\Models\DocumentKind;
use App\Models\DocumentRule;
use App\Models\ScholarshipProgramme;
use Illuminate\Database\Seeder;

/**
 * Document rules for the Chinese Government Scholarship (CSC), from the 2026/2027 application notices of the Chinese
 * Embassy in Indonesia and the Chinese Consulate General in Montreal (items a to m), and the CSC application form's
 * supporting-documents list. Safe to run again: it updates the same rules. Staff can change any rule in the admin afterwards,
 * but running this seeder again puts the values below back.
 *
 *   php artisan db:seed --class=CscDocumentRulesSeeder
 */
class CscDocumentRulesSeeder extends Seeder
{
    public const SLUG = 'chinese-government-scholarship-csc';

    /** kind key => [requirement, note] for every degree level of the scholarship. */
    private const SCHOLARSHIP = [
        'photo' => ['required', 'A passport-style photo, as listed with the CSC application form.'],
        'passport' => ['required', 'A clear scan of the page with your photo and details. If it is valid for less than 12 months from your expected start date, renew it before applying.'],
        'degree_certificate' => ['required', 'A notarised copy of your highest diploma. If you have not graduated yet, official proof of student status from your school. A diploma not in Chinese or English needs a notarised Chinese or English translation.'],
        'transcript' => ['required', 'Your academic transcripts. Transcripts not in Chinese or English need a notarised Chinese or English translation.'],
        'pre_admission_letter' => ['required', 'A pre-admission notice or invitation letter from a university (or professor) you applied to. At least one is needed.'],
        'study_plan' => ['required', 'In Chinese or English. Minimum length: 200 words for bachelor\'s, 500 words for non-degree students, 800 words for postgraduates.'],
        'police_clearance' => ['required', 'From the local public security authority, usually issued within 6 months before you apply. If you cannot get one, talk to your counsellor: the CSC form allows a non-criminal-record commitment signed with the university.'],
        'medical' => ['required', 'The Foreigner Physical Examination Form, in English, fully completed with the doctor\'s signature, hospital stamp and sealed photo. It is valid for 6 months, so time the examination carefully. Needed if you will stay in China more than 6 months.'],
        'chinese_language' => ['optional', 'Needed only if your programme is taught in Chinese: a valid HSK report, level 3 for bachelor\'s and level 4 for master\'s and doctoral.'],
        'english_language' => ['optional', 'Needed if your programme is taught in a language other than Chinese: proof such as IELTS or TOEFL, as each university requires.'],
        'recommendation' => ['optional', 'Master\'s, doctoral and senior-scholar applicants need two letters (see the degree level rules). Chinese or English, from professors or associate professors.'],
        'portfolio' => ['optional', 'Art and design applicants may upload their own works.'],
        'guardian_documents' => ['optional', 'Applicants under 18 must provide the valid documents of their legal guardians in China.'],
        'csca' => ['not_needed', 'Only for bachelor\'s applicants.'],
    ];

    /** degree level code => kind key => [requirement, note]. These beat the scholarship-wide rule above. */
    private const LEVELS = [
        'bachelor' => [
            'csca' => ['required', 'Register for and take the CSCA before the deadline and upload your valid score report. Ask the target university which test subjects it needs.'],
        ],
        'master' => [
            'recommendation' => ['required', 'Two letters of recommendation, in Chinese or English, from professors or associate professors.'],
        ],
        'doctorate' => [
            'recommendation' => ['required', 'Two letters of recommendation, in Chinese or English, from professors or associate professors.'],
        ],
    ];

    public function run(): void
    {
        $programme = ScholarshipProgramme::where('slug', self::SLUG)->first();
        if (! $programme) {
            $this->command?->warn('The CSC scholarship ('.self::SLUG.') is not in this database, so no rules were set.');

            return;
        }
        $kinds = DocumentKind::pluck('id', 'key');

        foreach (self::SCHOLARSHIP as $key => [$requirement, $note]) {
            $this->put($kinds[$key], $programme->id, null, $requirement, $note);
        }
        foreach (self::LEVELS as $code => $rules) {
            $level = DegreeLevel::where('code', $code)->first();
            foreach ($level ? $rules : [] as $key => [$requirement, $note]) {
                $this->put($kinds[$key], $programme->id, $level->id, $requirement, $note);
            }
        }
    }

    private function put(int $kindId, int $programmeId, ?int $levelId, string $requirement, string $note): void
    {
        $rule = DocumentRule::firstOrNew(['kind_id' => $kindId, 'programme_id' => $programmeId, 'degree_level_id' => $levelId]);
        $rule->fill(['requirement' => $requirement, 'note' => $note])->save();
    }
}
