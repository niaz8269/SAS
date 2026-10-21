<?php

namespace App\Filament\Resources\DegreeLevels\Pages;

use App\Filament\Resources\DegreeLevels\DegreeLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDegreeLevels extends ManageRecords
{
    protected static string $resource = DegreeLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}