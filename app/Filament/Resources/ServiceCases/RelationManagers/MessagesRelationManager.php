<?php

namespace App\Filament\Resources\ServiceCases\RelationManagers;

use App\Domain\Cases\CaseService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The conversation with the student. A public reply notifies them; an internal note is staff-only and is never shown in the student workspace. */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Messages';

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
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i'),
                TextColumn::make('author.name')->label('From')->placeholder('SAS team'),
                TextColumn::make('visibility')->label('Seen by')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'internal' ? 'Staff only' : 'Student')
                    ->color(fn ($state) => $state === 'internal' ? 'warning' : 'gray'),
                TextColumn::make('body')->label('Message')->wrap()->limit(400),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                Action::make('reply')->label('Reply or add a note')->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn () => (bool) auth()->user()?->can('update', $this->getOwnerRecord()))
                    ->schema([
                        Radio::make('visibility')->label('Who can see this')->options(['public' => 'Reply to the student (they are notified)', 'internal' => 'Internal note (staff only)'])->default('public')->required(),
                        Textarea::make('body')->label('Message')->required()->minLength(2)->maxLength(2000)->rows(5),
                    ])
                    ->action(function (array $data) {
                        app(CaseService::class)->postMessage($this->getOwnerRecord(), auth()->user(), $data['body'], $data['visibility']);
                        Notification::make()->title($data['visibility'] === 'public' ? 'Reply sent to the student' : 'Note saved')->success()->send();
                    }),
            ]);
    }
}
