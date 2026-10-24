<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Domain\Counselling\Counselling;
use App\Models\ReplyTemplate;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** The conversation with the student. The student reads and answers it in their workspace and is notified of each reply. */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'counsellorMessages';

    protected static ?string $title = 'Messages';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('view', $ownerRecord);
    }

    /** Not read only: the buttons on this tab are the point of it (read access is decided by the policies). */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i'),
                TextColumn::make('author.name')->label('From')->placeholder('SAS team')->badge()->color(fn ($record) => $record->fromStudent() ? 'warning' : 'gray'),
                TextColumn::make('body')->label('Message')->wrap()->limit(600),
                TextColumn::make('document.title')->label('File')->placeholder('-')->url(fn ($record) => $record->document && $record->document->currentVersion?->isDownloadable() ? route('files.download', $record->document) : null, shouldOpenInNewTab: true)->limit(30),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No messages yet')
            ->emptyStateDescription('Write to the student here. They are notified and reply in their workspace.')
            ->headerActions([
                Action::make('reply')->label('Write to the student')->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn () => (bool) auth()->user()?->can('update', $this->getOwnerRecord()))
                    ->schema([
                        Select::make('template')->label('Start from a saved reply')->placeholder('Write my own')->dehydrated(false)->live()
                            ->options(fn () => ReplyTemplate::usableBy(auth()->user())->orderBy('title')->pluck('title', 'id')->all())
                            ->afterStateUpdated(function ($state, Set $set) {
                                $template = $state ? ReplyTemplate::usableBy(auth()->user())->find($state) : null;
                                $template && $set('body', $template->fill_for($this->getOwnerRecord(), auth()->user()));
                            }),
                        Textarea::make('body')->label('Message')->required()->minLength(2)->maxLength(2000)->rows(6),
                        FileUpload::make('attachment')->label('Attach a file (optional)')->disk('local')->directory('tmp-attachments')->visibility('private')->maxSize(10240)
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->helperText('It is kept in the student\'s own documents after the usual virus check.'),
                    ])
                    ->action(function (array $data) {
                        $upload = null;
                        if ($path = $data['attachment'] ?? null) {
                            $upload = new UploadedFile(Storage::disk('local')->path($path), basename($path), null, null, true);
                        }
                        try {
                            app(Counselling::class)->message($this->getOwnerRecord(), auth()->user(), $data['body'], $upload);
                        } finally {
                            $path && Storage::disk('local')->delete($path);
                        }
                        Notification::make()->title('Message sent to the student')->success()->send();
                    }),
            ]);
    }
}
