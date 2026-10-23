<?php

namespace App\Filament\Resources\DocumentKinds\Pages;

use App\Filament\Resources\DocumentKinds\DocumentKindResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentKinds extends ListRecords
{
    protected static string $resource = DocumentKindResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add a document type')];
    }
}
