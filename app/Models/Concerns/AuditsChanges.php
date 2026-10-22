<?php

namespace App\Models\Concerns;

use App\Domain\Editorial\AuditLog;

/** Records who changed or deleted a website-content row (append-only audit trail). */
trait AuditsChanges
{
    protected static function bootAuditsChanges(): void
    {
        static::saved(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);
            if (auth()->check() && $changes !== []) {
                AuditLog::record('site.'.class_basename($model).($model->wasRecentlyCreated ? '_created' : '_updated'), $model,
                    $model->wasRecentlyCreated ? null : array_intersect_key($model->getOriginal(), $changes), $changes);
            }
        });
        static::deleted(function ($model) {
            if (auth()->check()) {
                AuditLog::record('site.'.class_basename($model).'_deleted', $model, $model->attributesToArray());
            }
        });
    }
}
