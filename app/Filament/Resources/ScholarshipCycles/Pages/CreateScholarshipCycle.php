<?php

namespace App\Filament\Resources\ScholarshipCycles\Pages;

use App\Enums\PublicationState;
use App\Filament\Resources\ScholarshipCycles\ScholarshipCycleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScholarshipCycle extends CreateRecord
{
    protected static string $resource = ScholarshipCycleResource::class;

    /** New cycles are always drafts owned by their creator; state is never taken from the form. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['publication_state'] = PublicationState::Draft->value;
        unset($data['featured'], $data['sponsored'], $data['sponsored_label'], $data['fully_funded_verified']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
