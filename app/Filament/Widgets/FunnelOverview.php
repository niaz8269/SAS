<?php

namespace App\Filament\Widgets;

use App\Models\AnalyticsEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** ANA-01 baseline (last 30 days). Counts only; the underlying events hold no personal data. Four numbers that tell the story. */
class FunnelOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Last 30 days';

    protected int|array|null $columns = 4;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('reports.view');
    }

    protected function getStats(): array
    {
        $since = now()->subDays(30);
        $count = fn (string $name) => AnalyticsEvent::where('name', $name)->where('created_at', '>=', $since)->count();

        $views = $count('scholarship_view');
        $clicks = $count('official_link_click');

        return [
            Stat::make('Scholarship views', number_format($views))->icon('heroicon-o-eye'),
            Stat::make('Official-link clicks', number_format($clicks))->description($views ? round($clicks / $views * 100).'% of views' : null)->icon('heroicon-o-arrow-top-right-on-square'),
            Stat::make('Enquiries', number_format($count('enquiry_submitted')))->icon('heroicon-o-envelope'),
            Stat::make('Consultation requests', number_format($count('consultation_requested')))->icon('heroicon-o-calendar-days'),
        ];
    }
}
