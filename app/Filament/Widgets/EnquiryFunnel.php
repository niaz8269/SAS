<?php

namespace App\Filament\Widgets;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use Filament\Widgets\Widget;

/** Where enquiries stand, as plain horizontal bars: new, contacted, qualified, quote issued, converted, closed. */
class EnquiryFunnel extends Widget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.enquiry-funnel';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('enquiries.view_all');
    }

    protected function getViewData(): array
    {
        $counts = Enquiry::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $rows = [];
        foreach (EnquiryStatus::cases() as $case) {
            $rows[] = ['label' => $case->label(), 'n' => (int) ($counts[$case->value] ?? 0), 'closed' => in_array($case, [EnquiryStatus::Closed], true)];
        }
        $max = max(1, collect($rows)->max('n'));

        return ['rows' => $rows, 'max' => $max, 'total' => array_sum(array_column($rows, 'n'))];
    }
}
