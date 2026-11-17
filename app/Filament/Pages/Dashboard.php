<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/** The staff home page: a greeting, the key figures, then what needs doing. */
class Dashboard extends BaseDashboard
{
    public function getTitle(): string|Htmlable
    {
        return 'Dashboard';
    }

    public function getHeading(): string|Htmlable
    {
        $first = trim((string) strtok((string) auth()->user()?->name, ' '));

        return 'Welcome back'.($first !== '' ? ', '.$first : '');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Here is what is happening across '.config('sas.brand.short').' today.';
    }
}
