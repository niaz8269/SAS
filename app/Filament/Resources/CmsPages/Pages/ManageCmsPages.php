<?php

namespace App\Filament\Resources\CmsPages\Pages;

use App\Filament\Resources\CmsPages\CmsPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCmsPages extends ManageRecords
{
    protected static string $resource = CmsPageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New page')->mutateDataUsing(fn (array $data) => $data + ['updated_by' => auth()->id()])];
    }
}
