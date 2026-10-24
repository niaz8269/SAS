<?php

namespace App\Filament\Resources\ReplyTemplates;

use App\Filament\Resources\ReplyTemplates\Pages\ManageReplyTemplates;
use App\Models\ReplyTemplate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Saved replies for messages to students. Shared ones are for everyone who counsels; your own are only yours. */
class ReplyTemplateResource extends Resource
{
    protected static ?string $model = ReplyTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'reply template';

    protected static ?string $navigationLabel = 'Reply templates';

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()->with('owner')->when(! $user?->hasPermission('students.manage'), fn (Builder $q) => $q->usableBy($user));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(120),
            Toggle::make('shared')->label('Share with every counsellor')->visible(fn (string $operation) => $operation === 'create' && auth()->user()?->hasPermission('students.manage'))
                ->default(false)->dehydrated(true),
            Textarea::make('body')->label('Message')->required()->rows(6)->maxLength(2000)->columnSpanFull()
                ->helperText('{name} becomes the student\'s name and {counsellor} becomes your name when you use it.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->weight('semibold'),
            TextColumn::make('body')->limit(90)->wrap(),
            TextColumn::make('owner.name')->label('Belongs to')->placeholder('Everyone'),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->defaultSort('title');
    }

    public static function getPages(): array
    {
        return ['index' => ManageReplyTemplates::route('/')];
    }
}
