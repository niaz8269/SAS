<?php

namespace App\Domain\Documents;

use App\Models\ApplicationForm;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Early warnings about the student's documents, before a funder rejects them. */
class DocumentHealth
{
    /** Months a passport should still be valid; most scholarships want 12 to 18 after the start of study. */
    public const WARN_MONTHS = 18;

    public const URGENT_MONTHS = 12;

    /** @return array{level:string,text:string}|null level is "urgent" or "soon" */
    public function passport(User $student): ?array
    {
        $raw = ApplicationForm::where('user_id', $student->id)->first()?->value('passport_expiry');
        if (blank($raw)) {
            return null;
        }
        try {
            $expiry = Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
        $on = $expiry->format('j M Y');
        if ($expiry->isPast()) {
            return ['level' => 'urgent', 'text' => "Your passport expired on {$on}. Renew it before you apply: no scholarship accepts an expired passport."];
        }
        if ($expiry->lt(now()->addMonths(self::URGENT_MONTHS))) {
            return ['level' => 'urgent', 'text' => "Your passport expires on {$on}, in less than 12 months. Most scholarships need it valid for 12 to 18 months after you start, so renew it now."];
        }
        if ($expiry->lt(now()->addMonths(self::WARN_MONTHS))) {
            return ['level' => 'soon', 'text' => "Your passport expires on {$on}. Some scholarships want 18 months of validity after you start, so check the one you chose."];
        }

        return null;
    }

    /** Every warning for a student, as plain lines. */
    public function warnings(User $student): array
    {
        return array_values(array_filter([$this->passport($student)]));
    }
}
