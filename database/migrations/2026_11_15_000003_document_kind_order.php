<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** SAS's Master's/PhD list comes first, in its own order; the other document types follow. */
    private const ORDER = [
        'passport', 'photo', 'degree_certificate', 'transcript', 'english_language', 'recommendation', 'study_plan', 'medical', 'cv', 'employment_letter',
        'police_clearance', 'publications',
        'pre_admission_letter', 'chinese_language', 'csca', 'research_proposal', 'statement', 'language_certificate', 'test_scores', 'writing_sample',
        'video_statement', 'portfolio', 'financial_proof', 'birth_certificate', 'nomination_letter', 'guardian_documents',
    ];

    public function up(): void
    {
        foreach (self::ORDER as $i => $key) {
            DB::table('document_kinds')->where('key', $key)->update(['sort' => ($i + 1) * 10]);
        }
        DB::table('document_kinds')->whereNotIn('key', self::ORDER)->where('key', '!=', 'other')->update(['sort' => 400]); // anything added later
    }

    public function down(): void {}
};
