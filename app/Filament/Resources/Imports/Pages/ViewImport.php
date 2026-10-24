<?php

namespace App\Filament\Resources\Imports\Pages;

use App\Domain\Editorial\CatalogueImporter;
use App\Domain\Editorial\ImportTemplates;
use App\Domain\Editorial\ScholarshipImporter;
use App\Filament\Resources\Imports\ImportResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewImport extends ViewRecord
{
    protected static string $resource = ImportResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Preview')->columns(4)->schema([
                TextEntry::make('filename'), TextEntry::make('kind')->label('Kind')->formatStateUsing(fn ($state) => ImportTemplates::KINDS[$state] ?? $state), TextEntry::make('status')->badge(),
                TextEntry::make('total_rows'), TextEntry::make('valid_rows'),
                TextEntry::make('error_rows')->color('danger'),
                TextEntry::make('summary.creates')->label('Will create'), TextEntry::make('summary.updates')->label('Will update'),
                TextEntry::make('summary.error')->label('Batch error')->visible(fn ($record) => isset($record->summary['error'])),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('commit')->label('Commit valid rows as drafts')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn () => in_array($this->getRecord()->status, ['validated'], true) && $this->getRecord()->valid_rows > 0 && auth()->user()->can('import.stage'))
                ->requiresConfirmation()->modalDescription(fn () => $this->getRecord()->kind === 'scholarships'
                    ? 'Valid rows become DRAFT cycles. Invalid rows are skipped. Nothing is published; every cycle still needs review.'
                    : 'Valid rows are added hidden from the public pages, and rows for things that already exist only fill empty fields. Invalid rows are skipped. You publish the new items yourself afterwards.')
                ->action(function () {
                    $import = $this->getRecord();
                    $import->kind === 'scholarships'
                        ? app(ScholarshipImporter::class)->commit($import, auth()->user())
                        : app(CatalogueImporter::class)->commit($import, auth()->user());
                    Notification::make()->title($import->kind === 'scholarships' ? 'Import committed. Cycles were created or updated as drafts.' : 'Import committed. New items are hidden until you publish them.')->success()->send();
                    $import->refresh();
                }),
            Action::make('errors')->label('Download error file')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->visible(fn () => $this->getRecord()->error_rows > 0)
                ->action(fn () => response()->streamDownload(
                    fn () => print ($this->getRecord()->kind === 'scholarships' ? ScholarshipImporter::errorCsv($this->getRecord()) : CatalogueImporter::errorCsv($this->getRecord())),
                    'import-errors-'.$this->getRecord()->id.'.csv', ['Content-Type' => 'text/csv'])),
        ];
    }
}
