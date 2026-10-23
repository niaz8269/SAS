<?php

namespace App\Filament\Resources\ServiceCases\RelationManagers;

use App\Models\Invoice;
use Filament\Tables\Columns\TextColumn;

class InvoiceColumns
{
    /** @return list<TextColumn> */
    public static function all(): array
    {
        return [
            TextColumn::make('description')->wrap()->limit(70)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('kind')->badge()->formatStateUsing(fn ($state) => Invoice::KINDS[$state] ?? $state),
            TextColumn::make('amount')->state(fn (Invoice $r) => $r->amount()),
            TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => Invoice::STATUSES[$state] ?? $state)
                ->color(fn ($state) => match ($state) {
                    'paid' => 'success', 'void' => 'gray', default => 'warning'
                }),
            TextColumn::make('refund_status')->label('Refund')->state(fn (Invoice $r) => match ($r->refund_status) {
                'due' => 'Due: '.$r->refundAmount(), 'paid' => 'Refunded '.$r->refundAmount(), default => ''
            })
                ->badge()->color(fn ($state) => str_starts_with((string) $state, 'Due') ? 'danger' : 'success'),
            TextColumn::make('paid_at')->dateTime('j M Y')->placeholder('-'),
            TextColumn::make('payment_reference')->label('Reference')->placeholder('-')->toggleable(),
        ];
    }
}
