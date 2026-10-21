<?php

namespace App\Filament\Resources\Imports\Pages;

use App\Domain\Editorial\ScholarshipImporter;
use App\Filament\Resources\Imports\ImportResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImports extends ListRecords
{
    protected static string $resource = ImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')->label('Download CSV template')->icon('heroicon-o-document-arrow-down')->color('gray')
                ->action(fn () => response()->streamDownload(fn () => print (ScholarshipImporter::templateCsv()), 'scholarship-import-template.csv', ['Content-Type' => 'text/csv'])),
            CreateAction::make()->label('Upload CSV'),
        ];
    }
}
