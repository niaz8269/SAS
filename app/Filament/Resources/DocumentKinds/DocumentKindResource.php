<?php

namespace App\Filament\Resources\DocumentKinds;

use App\Filament\Resources\DocumentKinds\Pages\CreateDocumentKind;
use App\Filament\Resources\DocumentKinds\Pages\EditDocumentKind;
use App\Filament\Resources\DocumentKinds\Pages\ListDocumentKinds;
use App\Filament\Resources\DocumentKinds\RelationManagers\RulesRelationManager;
use App\Models\DocumentKind;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * The documents SAS asks students for, and whether each is mandatory by default.
 * Scholarship- and degree-level-specific rules are added on each document (see Rules) and on each scholarship call.
 */
class DocumentKindResource extends Resource
{
    protected static ?string $model = DocumentKind::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'document type';

    protected static ?string $navigationLabel = 'Required documents';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120)->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, ?DocumentKind $record) => $record ? null : $set('key', Str::slug((string) $state, '_'))),
            TextInput::make('key')->required()->maxLength(40)->alphaDash()->unique(DocumentKind::class, 'key', ignoreRecord: true)
                ->disabled(fn (?DocumentKind $record) => $record !== null)->dehydrated(fn (?DocumentKind $record) => $record === null)
                ->helperText('Used internally. It cannot be changed after the document type is created.'),
            Textarea::make('description')->rows(2)->maxLength(300)->helperText('Shown to the student under the document name.')->columnSpanFull(),
            FileUpload::make('template_path')->label('Blank form for students to download')->disk('local')->directory('document-templates')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'])->maxSize(5120)->downloadable()
                ->helperText('Optional. A blank form (PDF or Word) the student downloads, fills in and uploads here. A "Download blank form" button appears on this document.')->columnSpanFull(),
            Select::make('default_requirement')->label('By default this document is')->options(DocumentKind::REQUIREMENTS)->required()->default('optional')
                ->helperText('Used when no rule below matches the student\'s scholarship and degree level.'),
            TextInput::make('sort')->numeric()->default(100)->helperText('Lower numbers are listed first.'),
            Toggle::make('active')->default(true)->helperText('Switched off: students can no longer choose it for new uploads.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->wrap(),
            TextColumn::make('default_requirement')->label('By default')->badge()->formatStateUsing(fn ($state) => DocumentKind::REQUIREMENTS[$state] ?? $state)
                ->color(fn ($state) => match ($state) {
                    'required' => 'danger', 'not_needed' => 'gray', default => 'info'
                }),
            TextColumn::make('rules_count')->counts('rules')->label('Special rules'),
            IconColumn::make('active')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->defaultSort('sort')->reorderable(false);
    }

    public static function getRelations(): array
    {
        return [RulesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListDocumentKinds::route('/'), 'create' => CreateDocumentKind::route('/create'), 'edit' => EditDocumentKind::route('/{record}/edit')];
    }
}
