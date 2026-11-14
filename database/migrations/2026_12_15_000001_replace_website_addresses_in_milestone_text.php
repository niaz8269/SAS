<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The owner asked (15 November 2026) that no official or university website address is shown to students: the public pages now link to www.cas.moe, and the
 * few website names inside the quoted source wording of timeline dates are replaced the same way. The stored source addresses (source_evidence) are kept as records.
 */
return new class extends Migration
{
    private const DOMAINS = ['apply.stipendiumhungaricum.hu', 'nawa.gov.pl', 'education-in-russia.com', 'studyinjapan.go.jp', 'studyin.gov.cz', 'studyinitaly.esteri.it'];

    public function up(): void
    {
        $pattern = '~(?:https?://|www\.)[^\s<>"\')]+|\b(?:'.implode('|', array_map(fn ($d) => preg_quote($d, '~'), self::DOMAINS)).')\b~i';
        DB::table('cycle_milestones')->whereNotNull('source_text')->orderBy('id')->each(function ($row) use ($pattern) {
            $new = preg_replace($pattern, 'www.cas.moe', $row->source_text);
            if ($new !== $row->source_text) {
                DB::table('cycle_milestones')->where('id', $row->id)->update(['source_text' => $new]);
            }
        });
    }

    public function down(): void
    {
        // The original wording is not kept here.
    }
};
