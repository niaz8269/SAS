<?php

namespace App\Filament\Pages;

use App\Domain\Admin\Desk;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** For admins: who has how much on their plate, so new work goes to the least busy person. */
class CounsellorWorkload extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Workload';

    protected static ?string $slug = 'workload';

    protected string $view = 'filament.pages.workload';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff() && auth()->user()->hasPermission('students.manage');
    }

    protected function getViewData(): array
    {
        $desk = app(Desk::class);
        $rows = $desk->workload()->sortBy('score')->values();

        return ['rows' => $rows, 'least' => $rows->first()['counsellor'] ?? null, 'max' => max(1, (int) $rows->max('score'))];
    }
}
