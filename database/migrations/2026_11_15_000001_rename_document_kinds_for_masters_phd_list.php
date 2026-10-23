<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** key => [name, description]. Names follow SAS's own Master's/PhD document list so students recognise each item. */
    private const NAMES = [
        'passport' => ['Passport', 'A clear scan of the page with your photo, and of two blank visa pages.'],
        'photo' => ['Passport-size photo (white background)', 'A recent, clear photo on a white background.'],
        'degree_certificate' => ['Notarised degree certificates', 'Notarised copies of your degrees.'],
        'transcript' => ['Notarised transcripts (marks sheets)', 'Notarised final transcripts or marks sheets.'],
        'english_language' => ['English proficiency letter', 'Proof of your English, such as a letter or test result.'],
        'recommendation' => ['Recommendation letters', 'Letters from teachers or employers.'],
        'study_plan' => ['Study plan or personal statement', 'What you plan to study or research, and why.'],
        'medical' => ['Physical examination form', 'The health examination form, completed by a doctor.'],
        'cv' => ['CV (resume)', null],
        'employment_letter' => ['Experience letter', 'A letter from an employer about your work experience, if you have one.'],
        'police_clearance' => ['Police no criminal record certificate', 'A certificate from the police that you have no criminal record.'],
        'publications' => ['Thesis, research articles or papers', 'Your thesis or published research, if you have any.'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $key => [$name, $description]) {
            DB::table('document_kinds')->where('key', $key)->update(['name' => $name, 'description' => $description, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Names are presentation only; the previous wording is not restored.
    }
};
