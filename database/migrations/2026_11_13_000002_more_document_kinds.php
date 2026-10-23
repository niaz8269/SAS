<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KINDS = [
        ['test_scores', 'Standardised test scores', 'For example GRE, GMAT or SAT, if the scholarship asks for them.', 112],
        ['writing_sample', 'Writing sample', 'An essay or academic writing sample, if the scholarship asks for it.', 113],
        ['video_statement', 'Video statement', 'A short video, if the scholarship asks for one.', 114],
    ];

    public function up(): void
    {
        foreach (self::KINDS as [$key, $name, $description, $sort]) {
            DB::table('document_kinds')->insertOrIgnore(['key' => $key, 'name' => $name, 'description' => $description, 'default_requirement' => 'optional', 'sort' => $sort, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('document_kinds')->whereIn('key', array_column(self::KINDS, 0))->delete();
    }
};
