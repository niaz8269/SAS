<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Widgets\Widget;

/** Billed, collected and still outstanding, from the invoices (cancelled ones are left out). */
class FeesOverview extends Widget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.fees-overview';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('finance.view');
    }

    protected function getViewData(): array
    {
        $billed = (int) Invoice::whereIn('status', ['issued', 'paid'])->sum('amount_cents');
        $collected = (int) Invoice::where('status', 'paid')->sum('amount_cents');
        $outstanding = $billed - $collected;

        return [
            'billed' => Invoice::money($billed),
            'collected' => Invoice::money($collected),
            'outstanding' => Invoice::money($outstanding),
            'percent' => $billed ? (int) round($collected / $billed * 100) : 0,
            'count' => Invoice::whereIn('status', ['issued', 'paid'])->count(),
            'url' => InvoiceResource::getUrl(),
        ];
    }
}
