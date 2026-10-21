<?php

namespace App\Filament\Resources\Funders\Pages;

use App\Filament\Resources\Funders\FunderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFunders extends ManageRecords
{
    protected static string $resource = FunderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
