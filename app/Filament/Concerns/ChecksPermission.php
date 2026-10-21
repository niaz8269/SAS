<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Filament resources that are not governed by a model policy are gated by one named role permission (AUTH-03).
 * Hiding the navigation entry is a side effect, never the protection: every action calls these checks.
 */
trait ChecksPermission
{
    abstract protected static function permission(): string;

    protected static function allowed(): bool
    {
        return (bool) auth()->user()?->can(static::permission());
    }

    public static function canViewAny(): bool
    {
        return static::allowed();
    }

    public static function canCreate(): bool
    {
        return static::allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return static::allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed();
    }

    public static function canDeleteAny(): bool
    {
        return static::allowed();
    }
}
