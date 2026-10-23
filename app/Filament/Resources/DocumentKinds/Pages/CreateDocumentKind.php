<?php

namespace App\Filament\Resources\DocumentKinds\Pages;

use App\Filament\Resources\DocumentKinds\DocumentKindResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentKind extends CreateRecord
{
    protected static string $resource = DocumentKindResource::class;
}
