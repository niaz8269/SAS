<?php

namespace App\Filament\Resources\ScholarshipCycles\Pages;

use App\Filament\Resources\ScholarshipCycles\ScholarshipCycleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScholarshipCycles extends ListRecords
{
    protected static string $resource = ScholarshipCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
