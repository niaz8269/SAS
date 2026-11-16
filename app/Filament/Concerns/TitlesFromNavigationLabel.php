<?php

namespace App\Filament\Concerns;

/**
 * One name for a screen everywhere: the page heading and the breadcrumb use the same words as the menu ("Menus", not "Menu Links"),
 * in sentence case like the rest of the admin. Resources without a menu label keep Filament's own title.
 */
trait TitlesFromNavigationLabel
{
    public static function getTitleCasePluralModelLabel(): string
    {
        return static::$navigationLabel ?: parent::getTitleCasePluralModelLabel();
    }
}
