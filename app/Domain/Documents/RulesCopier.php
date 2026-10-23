<?php

namespace App\Domain\Documents;

use App\Domain\Editorial\AuditLog;
use App\Models\DocumentRule;
use App\Models\ScholarshipProgramme;
use Illuminate\Support\Facades\DB;

/** Starts a scholarship's document rules from another scholarship's (for example a new call of a similar programme). */
class RulesCopier
{
    /**
     * @param  bool  $replace  true removes the target's existing rules first; false keeps them and only adds what is missing
     * @return int how many rules were added
     */
    public function copy(ScholarshipProgramme $from, ScholarshipProgramme $to, bool $replace = false): int
    {
        if ($from->is($to)) {
            return 0;
        }

        return DB::transaction(function () use ($from, $to, $replace) {
            $replace && DocumentRule::where('programme_id', $to->id)->delete();
            $added = 0;
            foreach (DocumentRule::where('programme_id', $from->id)->get() as $rule) {
                $copy = DocumentRule::firstOrNew(['kind_id' => $rule->kind_id, 'programme_id' => $to->id, 'degree_level_id' => $rule->degree_level_id]);
                if ($copy->exists) {
                    continue;
                }
                // checked_on stays empty: nobody has compared this rule with the new scholarship's own official pages yet.
                $copy->fill(['requirement' => $rule->requirement, 'note' => $rule->note, 'source_url' => $rule->source_url])->save();
                $added++;
            }
            AuditLog::record('document_rules.copied', $to, null, ['from' => $from->id, 'added' => $added, 'replaced' => $replace]);

            return $added;
        });
    }
}
