<?php

namespace App\Filament\Resources\Enquiries\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** COM-01: public replies reach the student; internal notes never do. */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Conversation and internal notes';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('visibility')->options(['public' => 'Reply to the student (they can see this)', 'internal' => 'Internal note (never shown to the student)'])->default('public')->required(),
            Textarea::make('body')->required()->rows(5)->maxLength(4000),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->dateTime('j M H:i'),
            TextColumn::make('visibility')->badge()->color(fn ($state) => $state === 'internal' ? 'warning' : 'success'),
            TextColumn::make('author.name')->placeholder('Student'),
            TextColumn::make('body')->wrap()->limit(300),
        ])->defaultSort('id')->headerActions([
            CreateAction::make()->label('Add reply or note')->mutateDataUsing(fn (array $data) => $data + ['author_id' => auth()->id()])
                ->after(function (Model $record) {
                    $enquiry = $this->getOwnerRecord();
                    if ($record->visibility === 'public' && ! $enquiry->first_response_at) {
                        $enquiry->forceFill(['first_response_at' => now()])->save(); // enquiry response-time reporting
                    }
                }),
        ]);
    }
}
