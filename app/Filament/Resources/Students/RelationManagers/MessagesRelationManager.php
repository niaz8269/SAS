<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Domain\Counselling\Counselling;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The conversation with the student. The student reads and answers it in their workspace and is notified of each reply. */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'counsellorMessages';

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
                TextColumn::make('author.name')->label('From')->placeholder('SAS team')->badge()->color(fn ($record) => $record->fromStudent() ? 'warning' : 'gray'),
                TextColumn::make('body')->label('Message')->wrap()->limit(600),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No messages yet')
            ->emptyStateDescription('Write to the student here. They are notified and reply in their workspace.')
            ->headerActions([
                Action::make('reply')->label('Write to the student')->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn () => (bool) auth()->user()?->can('update', $this->getOwnerRecord()))
                    ->schema([Textarea::make('body')->label('Message')->required()->minLength(2)->maxLength(2000)->rows(5)])
                    ->action(function (array $data) {
                        app(Counselling::class)->message($this->getOwnerRecord(), auth()->user(), $data['body']);
                        Notification::make()->title('Message sent to the student')->success()->send();
                    }),
            ]);
    }
}
