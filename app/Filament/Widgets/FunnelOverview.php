<?php

namespace App\Filament\Widgets;

use App\Models\AnalyticsEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** ANA-01 baseline (last 30 days). Counts only; the underlying events hold no personal data. */
class FunnelOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Student funnel, last 30 days';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('reports.view');
    }

    protected function getStats(): array
    {
        $since = now()->subDays(30);
        $count = fn (string $name) => AnalyticsEvent::where('name', $name)->where('created_at', '>=', $since)->count();

        $views = $count('scholarship_view');
        $stat = fn (string $label, string $name, ?string $hint = null) => Stat::make($label, $count($name))->description($hint);

        return [
            $stat('Scholarship views', 'scholarship_view'),
            $stat('Official-link clicks', 'official_link_click', $views ? round($count('official_link_click') / $views * 100).'% of views' : null),
            $stat('Saved', 'scholarship_saved'),
            $stat('Plans created', 'plan_created'),
            $stat('Enquiries', 'enquiry_submitted'),
            $stat('Consultation requests', 'consultation_requested'),
            $stat('Searches with no results', 'zero_result_search', 'Content gaps to fill'),
        ];
    }
}
