<?php

namespace App\Filament\Resources\DocumentKinds\Pages;

use App\Filament\Resources\DocumentKinds\DocumentKindResource;
use Filament\Resources\Pages\EditRecord;

class EditDocumentKind extends EditRecord
{
    protected static string $resource = DocumentKindResource::class;
}
