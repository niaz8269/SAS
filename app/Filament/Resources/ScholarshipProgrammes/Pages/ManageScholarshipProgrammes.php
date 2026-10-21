<?php

namespace App\Filament\Resources\ScholarshipProgrammes\Pages;

use App\Filament\Resources\ScholarshipProgrammes\ScholarshipProgrammeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageScholarshipProgrammes extends ManageRecords
{
    protected static string $resource = ScholarshipProgrammeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(function (array $data) {
            $data['created_by'] = auth()->id();

            return $data;
        })];
    }
}
