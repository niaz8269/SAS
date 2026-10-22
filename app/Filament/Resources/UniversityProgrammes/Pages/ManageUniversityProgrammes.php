<?php

namespace App\Filament\Resources\UniversityProgrammes\Pages;

use App\Filament\Resources\UniversityProgrammes\UniversityProgrammeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUniversityProgrammes extends ManageRecords
{
    protected static string $resource = UniversityProgrammeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add a programme')];
    }
}
