<?php

namespace App\Filament\Pages;

use App\Domain\Counselling\CounsellorReport as Report;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** For managers: how each counsellor's students are doing. */
class CounsellorReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Counsellor report';

    protected static ?string $slug = 'counsellor-report';

    protected string $view = 'filament.pages.counsellor-report';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff() && auth()->user()->hasPermission('students.manage');
    }

    protected function getViewData(): array
    {
        $report = app(Report::class);

        return ['rows' => $report->rows(), 'labels' => $report->statusLabels()];
    }
}
