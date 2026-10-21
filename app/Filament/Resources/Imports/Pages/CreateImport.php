<?php

namespace App\Filament\Resources\Imports\Pages;

use App\Domain\Editorial\ScholarshipImporter;
use App\Filament\Resources\Imports\ImportResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateImport extends CreateRecord
{
    protected static string $resource = ImportResource::class;

    protected static bool $canCreateAnother = false;

    /** Staging only parses and validates. Nothing reaches the scholarship tables until the operator commits the preview. */
    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(auth()->user()->can('import.stage'), 403);
        $stored = $data['file'];
        $path = Storage::disk('local')->path($stored);
        $import = app(ScholarshipImporter::class)->stage($path, basename($stored), auth()->user());
        Storage::disk('local')->delete($stored); // the parsed rows are kept in import_rows; the upload is not retained

        return $import;
    }

    protected function getRedirectUrl(): string
    {
        return ImportResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
