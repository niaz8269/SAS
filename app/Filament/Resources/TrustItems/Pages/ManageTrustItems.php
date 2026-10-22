<?php

namespace App\Filament\Resources\TrustItems\Pages;

use App\Filament\Resources\TrustItems\TrustItemResource;
use App\Models\TrustItem;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTrustItems extends ManageRecords
{
    protected static string $resource = TrustItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add an item')->mutateDataUsing(fn (array $data) => $data + ['sort' => ((int) TrustItem::max('sort')) + 10])];
    }
}
