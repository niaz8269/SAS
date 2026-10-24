<?php

namespace App\Filament\Resources\UniversityScholarships\Pages;

use App\Filament\Resources\UniversityScholarships\UniversityScholarshipResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUniversityScholarships extends ManageRecords
{
    protected static string $resource = UniversityScholarshipResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add a scholarship')];
    }
}
