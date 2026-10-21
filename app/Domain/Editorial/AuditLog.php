<?php

namespace App\Domain\Editorial;

use App\Models\Audit;
use Illuminate\Database\Eloquent\Model;

class AuditLog
{
    public static function record(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null, ?string $reason = null, $actor = null): Audit
    {
        return Audit::create([
            'actor_id' => ($actor ?? auth()->user())?->getAuthIdentifier(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
