<?php

namespace App\Filament\Resources\ServiceCases\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Only documents the student chose to share on this case, while the share lasts. Opening one goes through DocumentPolicy and is audited. */
class SharedDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'shares';

    protected static ?string $title = 'Documents shared by the student';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('document.currentVersion'))
            ->columns([
                TextColumn::make('document.title')->label('Document'),
                TextColumn::make('document.type')->label('Type')->formatStateUsing(fn ($state) => config('sas.document_types.'.$state, $state)),
                TextColumn::make('shared_at')->label('Shared on')->dateTime('j M Y, H:i'),
            ])
            ->emptyStateHeading('No documents shared')
            ->emptyStateDescription('The student has not shared any documents on this case.')
            ->recordActions([
                Action::make('open')->label('Open')->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn ($record) => (bool) $record->document?->currentVersion?->isDownloadable())
                    ->url(fn ($record) => route('files.download', $record->document), shouldOpenInNewTab: true),
            ]);
    }
}
