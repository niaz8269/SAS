<?php

namespace App\Models\Concerns;

use App\Domain\Editorial\AuditLog;

/** Records who created, changed or deleted a website-content row (append-only audit trail). */
trait AuditsChanges
{
    protected static function bootAuditsChanges(): void
    {
        static::saved(function ($model) {
            if (! auth()->check()) {
                return;
            }
            $created = $model->wasRecentlyCreated;
            $changes = $created ? $model->attributesToArray() : $model->getChanges();
            unset($changes['updated_at'], $changes['created_at']);
            if ($changes !== []) {
                AuditLog::record('site.'.class_basename($model).($created ? '_created' : '_updated'), $model,
                    $created ? null : array_intersect_key($model->getOriginal(), $changes), $changes);
            }
        });
        static::deleted(function ($model) {
            if (auth()->check()) {
                AuditLog::record('site.'.class_basename($model).'_deleted', $model, $model->attributesToArray());
            }
        });
    }
}
