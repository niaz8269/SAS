<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Domain\Counselling\Counselling;
use App\Models\Document;
use App\Models\DocumentKind;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Every document the student has uploaded. Only the student's own counsellor can open or comment on them, and each opening is audited. */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->counsels($ownerRecord);
    }

    /** Not read only: the buttons on this tab are the point of it (read access is decided by the policies). */
    public function isReadOnly(): bool
    {
        return false;
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
                TextColumn::make('currentVersion.quality_note')->label('Check')->wrap()->limit(60)->placeholder('-')->color('warning'),
                TextColumn::make('latest_comment')->label('Comment')->wrap()->limit(60)->state(fn (Document $r) => $r->comments()->latest('id')->value('body'))->placeholder('-'),
                TextColumn::make('created_at')->label('Uploaded')->date('j M Y')->sortable(),
            ])
            ->defaultSort('type')
            ->emptyStateHeading('No documents uploaded')
            ->recordActions([
                Action::make('open')->label('Open')->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Document $record) => (bool) $record->currentVersion?->isDownloadable() && auth()->user()->can('download', $record))
                    ->url(fn (Document $record) => route('files.download', $record), shouldOpenInNewTab: true),
                Action::make('comment')->label('Comment')->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->visible(fn (Document $record) => (bool) auth()->user()->counsels($record->user))
                    ->modalHeading('Comment on this document')->modalDescription('The student sees it next to the file, for example "the scan is too dark, please upload it again".')
                    ->schema([Textarea::make('body')->label('Comment')->required()->maxLength(500)->rows(3)])
                    ->action(function (Document $record, array $data) {
                        app(Counselling::class)->commentOnDocument($record, auth()->user(), $data['body']);
                        Notification::make()->title('Comment saved and the student was told')->success()->send();
                    }),
            ]);
    }
}
