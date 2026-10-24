<?php

namespace App\Filament\Pages;

use App\Domain\Admin\Desk;
use App\Domain\Admin\DeskRows;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** For admins: everything that is unassigned, late or overdue, in one list with a link to each item. */
class NeedsAttention extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Needs attention';

    protected static ?string $slug = 'needs-attention';

    protected string $view = 'filament.pages.needs-attention';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->isStaff() && $user->hasPermission('cases.manage');
    }

    public static function getNavigationBadge(): ?string
    {
        $late = app(Desk::class)->lateCount();

        return $late > 0 ? (string) $late : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected function getViewData(): array
    {
        $desk = app(Desk::class);
        $groups = $desk->groups();

        return ['groups' => $groups, 'lateCount' => $desk->lateCount($groups), 'total' => $desk->total($groups), 'line' => fn (string $g, $row) => DeskRows::line($g, $row)];
    }
}
