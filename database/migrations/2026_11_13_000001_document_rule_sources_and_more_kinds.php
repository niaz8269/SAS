<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** [key, name, description, sort] documents that many scholarships (not only CSC) ask for. */
    private const KINDS = [
        ['research_proposal', 'Research proposal', 'A description of the research you plan to do.', 55],
        ['financial_proof', 'Proof of finances', 'A bank statement or similar, if the scholarship asks for it.', 125],
        ['birth_certificate', 'Birth certificate', null, 127],
        ['employment_letter', 'Employment or work experience letter', 'A letter from your employer, if the scholarship asks for it.', 128],
        ['nomination_letter', 'Nomination or endorsement letter', 'A letter from your government, university or employer nominating you, if required.', 129],
    ];

    public function up(): void
    {
        Schema::table('document_rules', function (Blueprint $t) {
            $t->string('source_url', 500)->nullable(); // the official page the rule comes from
            $t->date('checked_on')->nullable();        // when someone last compared it with that page
        });

        foreach (self::KINDS as [$key, $name, $description, $sort]) {
            DB::table('document_kinds')->insertOrIgnore(['key' => $key, 'name' => $name, 'description' => $description, 'default_requirement' => 'optional', 'sort' => $sort, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('document_rules', fn (Blueprint $t) => $t->dropColumn(['source_url', 'checked_on']));
        DB::table('document_kinds')->whereIn('key', array_column(self::KINDS, 0))->delete();
    }
};
