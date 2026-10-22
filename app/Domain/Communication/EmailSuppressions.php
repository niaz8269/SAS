<?php

namespace App\Domain\Communication;

use App\Domain\Editorial\AuditLog;
use App\Models\EmailSuppression;

/** Hard bounces and complaints stop all further email to that address until staff lift the block. */
class EmailSuppressions
{
    public function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function isSuppressed(string $email): bool
    {
        return EmailSuppression::where('email', $this->normalise($email))->whereNull('lifted_at')->exists();
    }

    public function suppress(string $email, string $reason, ?string $source = null): void
    {
        $row = EmailSuppression::firstOrNew(['email' => $this->normalise($email)]);
        $new = ! $row->exists || $row->lifted_at !== null;
        $row->fill(['reason' => $reason, 'source' => $source])->forceFill(['lifted_at' => null])->save();
        if ($new) {
            AuditLog::record('email.suppressed', $row, null, ['reason' => $reason, 'source' => $source]); // the address itself is in the row, not the log
        }
    }

    public function lift(EmailSuppression $row, $actor = null): void
    {
        $row->forceFill(['lifted_at' => now()])->save();
        AuditLog::record('email.suppression_lifted', $row, null, null, null, $actor);
    }
}
