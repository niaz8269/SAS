<?php

namespace App\Filament\Resources\Imports\Pages;

use App\Domain\Editorial\CatalogueImporter;
use App\Domain\Editorial\ImportTemplates;
use App\Domain\Editorial\ScholarshipImporter;
use App\Domain\Editorial\SpreadsheetFile;
use App\Filament\Resources\Imports\ImportResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreateImport extends CreateRecord
{
    protected static string $resource = ImportResource::class;

    protected static bool $canCreateAnother = false;

    /** Staging only parses and validates. Nothing reaches the live tables until the operator commits the preview. */
    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(auth()->user()->can('import.stage'), 403);
        $stored = $data['file'];
        $path = Storage::disk('local')->path($stored);
        $kind = array_key_exists($data['kind'] ?? '', ImportTemplates::KINDS) ? $data['kind'] : 'scholarships';
        $converted = null;

        try {
            if (strtolower(pathinfo($stored, PATHINFO_EXTENSION)) === 'xlsx') {
                try {
                    $path = $converted = SpreadsheetFile::toCsv($path); // an Excel file is read as its first sheet
                } catch (\Throwable) {
                    throw ValidationException::withMessages(['data.file' => 'The Excel file could not be read. Open it, save it again as .xlsx or CSV and upload that.']);
                }
            }
            $import = $kind === 'scholarships'
                ? app(ScholarshipImporter::class)->stage($path, basename($stored), auth()->user())
                : app(CatalogueImporter::class)->stage($path, basename($stored), $kind, auth()->user());
        } finally {
            $converted && @unlink($converted);
            Storage::disk('local')->delete($stored); // the parsed rows are kept in import_rows; the upload is not retained
        }

        return $import;
    }

    protected function getRedirectUrl(): string
    {
        return ImportResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
