<?php

namespace App\Filament\Resources\Imports;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\Imports\Pages\CreateImport;
use App\Filament\Resources\Imports\Pages\ListImports;
use App\Filament\Resources\Imports\Pages\ViewImport;
use App\Filament\Resources\Imports\RelationManagers\RowsRelationManager;
use App\Models\Import;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** IMP-01..03 staging area: upload, validate, preview, then commit. Imports can only create or update drafts. */
class ImportResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = Import::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|\UnitEnum|null $navigationGroup = 'Editorial';

    protected static ?int $navigationSort = 3;

    protected static function permission(): string
    {
        return 'import.stage';
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('file')->label('CSV file (UTF-8)')->required()->disk('local')->directory('imports')->visibility('private')
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])->maxSize(5120)
                ->helperText('Use the template: ISO dates, IANA time zones, ISO country codes, source URLs. Include no student data. Nothing is published by an import.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->dateTime('j M Y H:i')->sortable(),
            TextColumn::make('filename'),
            TextColumn::make('status')->badge()->color(fn ($s) => match ($s) { 'committed' => 'success', 'validated' => 'info', 'failed' => 'danger', default => 'gray' }),
            TextColumn::make('total_rows')->label('Rows'), TextColumn::make('valid_rows')->label('Valid'), TextColumn::make('error_rows')->label('Errors')->color('danger'),
        ])->recordActions([ViewAction::make()])->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [RowsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImports::route('/'), 'create' => CreateImport::route('/create'), 'view' => ViewImport::route('/{record}'),
        ];
    }
}
