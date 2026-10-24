<?php

namespace App\Filament\Widgets;

use App\Domain\Admin\Desk;
use App\Filament\Pages\NeedsAttention;
use Filament\Widgets\Widget;

/** A one-line reminder on the admin dashboard that links to the Needs attention list. */
class AdminAttention extends Widget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.admin-attention';

    public static function canView(): bool
    {
        return NeedsAttention::canAccess();
    }

    protected function getViewData(): array
    {
        $desk = app(Desk::class);
        $groups = $desk->groups();

        return ['late' => $desk->lateCount($groups), 'total' => $desk->total($groups), 'url' => NeedsAttention::getUrl()];
    }
}
