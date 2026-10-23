<?php

namespace App\Filament\Resources\PaymentOptions\Pages;

use App\Filament\Resources\PaymentOptions\PaymentOptionResource;
use App\Models\PaymentOption;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentOptions extends ManageRecords
{
    protected static string $resource = PaymentOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add a payment option')->mutateDataUsing(fn (array $data) => $data + ['sort' => ((int) PaymentOption::max('sort')) + 10])];
    }
}
