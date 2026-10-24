<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Models\Document;
use App\Models\DocumentKind;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Every document the student has uploaded. Only the student's own counsellor can open them, and each opening is audited. */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->counsels($ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('currentVersion'))
            ->columns([
                TextColumn::make('title')->label('Document')->searchable()->wrap(),
                TextColumn::make('type')->label('Type')->formatStateUsing(fn ($state) => DocumentKind::label($state))->sortable(),
                TextColumn::make('currentVersion.original_name')->label('File')->limit(40)->placeholder('-'),
                TextColumn::make('currentVersion.scan_status')->label('Virus check')->badge()->formatStateUsing(fn ($state) => $state?->label() ?? '-'),
                TextColumn::make('created_at')->label('Uploaded')->date('j M Y')->sortable(),
            ])
            ->defaultSort('type')
            ->emptyStateHeading('No documents uploaded')
            ->recordActions([
                Action::make('open')->label('Open')->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Document $record) => (bool) $record->currentVersion?->isDownloadable() && auth()->user()->can('download', $record))
                    ->url(fn (Document $record) => route('files.download', $record), shouldOpenInNewTab: true),
            ]);
    }
}
