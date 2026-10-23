<?php

namespace App\Filament\Resources\ServiceCases\Pages;

use App\Filament\Resources\ServiceCases\ServiceCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceCases extends ListRecords
{
    protected static string $resource = ServiceCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Open a case')];
    }
}
