<?php

namespace App\Filament\Resources\Imports\Pages;

use App\Domain\Editorial\ImportTemplates;
use App\Filament\Resources\Imports\ImportResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImports extends ListRecords
{
    protected static string $resource = ImportResource::class;

    protected function getHeaderActions(): array
    {
        $templates = [];
        foreach (ImportTemplates::KINDS as $kind => $label) {
            $templates[] = Action::make('template_'.$kind.'_xlsx')->label($label.' (Excel, with instructions)')->icon('heroicon-o-table-cells')
                ->action(fn () => response()->download(ImportTemplates::xlsx($kind), 'sas-import-'.$kind.'-template.xlsx')->deleteFileAfterSend());
        }
        foreach (ImportTemplates::KINDS as $kind => $label) {
            $templates[] = Action::make('template_'.$kind.'_csv')->label($label.' (CSV)')->icon('heroicon-o-document-arrow-down')
                ->action(fn () => response()->streamDownload(fn () => print (ImportTemplates::csv($kind)), 'sas-import-'.$kind.'-template.csv', ['Content-Type' => 'text/csv']));
        }

        return [
            ActionGroup::make($templates)->label('Download a template')->icon('heroicon-o-document-arrow-down')->color('gray')->button(),
            CreateAction::make()->label('Upload a file'),
        ];
    }
}
