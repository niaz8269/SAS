<?php

namespace App\Filament\Resources\ServiceCases\RelationManagers;

use App\Domain\Cases\CaseService;
use App\Models\Invoice;
use App\Models\PaymentOption;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use InvalidArgumentException;

class InvoiceActions
{
    /** @return list<Action> */
    public static function all(): array
    {
        $run = function (callable $do, string $done) {
            try {
                $do();
                Notification::make()->title($done)->success()->send();
            } catch (InvalidArgumentException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();
            }
        };

        return [
            Action::make('markPaid')->label('Mark as paid')->icon('heroicon-o-banknotes')->color('success')
                ->visible(fn (Invoice $r) => $r->status === 'issued' && auth()->user()->can('update', $r))
                ->schema([
                    Select::make('payment_option_id')->label('Paid by')->options(fn () => PaymentOption::query()->orderBy('sort')->pluck('name', 'id')->all())->placeholder('Other / not listed'),
                    TextInput::make('reference')->label('Payment reference or receipt number')->maxLength(120),
                ])
                ->action(fn (Invoice $r, array $data) => $run(fn () => app(CaseService::class)->markPaid($r, ! empty($data['payment_option_id']) ? PaymentOption::find($data['payment_option_id']) : null, $data['reference'] ?? null, auth()->user()), 'Payment recorded')),
            Action::make('void')->label('Cancel invoice')->icon('heroicon-o-x-circle')->color('gray')->requiresConfirmation()
                ->visible(fn (Invoice $r) => $r->status === 'issued' && auth()->user()->can('update', $r))
                ->action(fn (Invoice $r) => $run(fn () => app(CaseService::class)->void($r, auth()->user()), 'Invoice cancelled')),
            Action::make('markRefunded')->label('Record refund paid')->icon('heroicon-o-arrow-uturn-left')->color('warning')->requiresConfirmation()
                ->visible(fn (Invoice $r) => $r->refund_status === 'due' && auth()->user()->can('update', $r))
                ->action(fn (Invoice $r) => $run(fn () => app(CaseService::class)->markRefunded($r, auth()->user()), 'Refund recorded')),
        ];
    }
}
