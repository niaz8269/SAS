<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Domain\Agents\AgentMessaging;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** What the student's recruiting agency and SAS have said about the student. The student never sees this. */
class AgentMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'agentMessages';

    protected static ?string $title = 'Agent messages';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->managed_by_organisation_id !== null && (bool) auth()->user()?->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i'),
                TextColumn::make('author.name')->label('From')->badge()->color(fn ($record) => $record->from_agent ? 'warning' : 'gray')->placeholder('-'),
                TextColumn::make('body')->label('Message')->wrap()->limit(600),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Nothing from the agency yet')
            ->headerActions([
                Action::make('answer')->label('Write to the agency')->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn () => (bool) auth()->user()?->can('update', $this->getOwnerRecord()))
                    ->schema([Textarea::make('body')->label('Message')->required()->minLength(2)->maxLength(2000)->rows(5)])
                    ->action(function (array $data) {
                        app(AgentMessaging::class)->fromStaff($this->getOwnerRecord(), auth()->user(), $data['body']);
                        Notification::make()->title('Sent to the agency')->success()->send();
                    }),
            ]);
    }
}
