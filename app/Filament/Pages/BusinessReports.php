<?php

namespace App\Filament\Pages;

use App\Domain\Admin\BusinessReport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/** For admins: enquiries, students, cases, applications, money and agents for a date range, on screen and as CSV. */
class BusinessReports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Reports';

    protected static ?string $slug = 'reports';

    protected string $view = 'filament.pages.business-reports';

    public ?string $from = null;

    public ?string $to = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff() && auth()->user()->hasPermission('reports.view');
    }

    public function mount(): void
    {
        $this->from = now()->subDays(29)->toDateString();
        $this->to = now()->toDateString();
    }

    /** Dates typed by hand can be wrong: fall back to the last 30 days rather than failing. */
    private function range(): array
    {
        try {
            $from = Carbon::parse($this->from);
            $to = Carbon::parse($this->to);
        } catch (\Throwable) {
            return [now()->subDays(29), now()];
        }

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    protected function getViewData(): array
    {
        [$from, $to] = $this->range();

        return ['sections' => app(BusinessReport::class)->sections($from, $to), 'query' => http_build_query(['from' => $from->toDateString(), 'to' => $to->toDateString()])];
    }
}
