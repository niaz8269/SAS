<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AgentReport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** For admins: how each recruiting agency is doing. */
class AgentOverview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruiting agents';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Agent overview';

    protected static ?string $slug = 'agent-overview';

    protected string $view = 'filament.pages.agent-overview';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff() && auth()->user()->hasPermission('partners.manage');
    }

    protected function getViewData(): array
    {
        return ['rows' => app(AgentReport::class)->rows()];
    }
}
