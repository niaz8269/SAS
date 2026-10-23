<?php

namespace App\Filament\Resources\PartnerOrganisations\Pages;

use App\Filament\Resources\PartnerOrganisations\PartnerOrganisationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartnerOrganisations extends ListRecords
{
    protected static string $resource = PartnerOrganisationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add a partner')];
    }
}
