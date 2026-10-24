<?php

namespace App\Filament\Resources\UniversityFeeStructures\Pages;

use App\Filament\Resources\UniversityFeeStructures\UniversityFeeStructureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUniversityFeeStructures extends ManageRecords
{
    protected static string $resource = UniversityFeeStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add a fee structure')];
    }
}
