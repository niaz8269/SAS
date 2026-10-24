<?php

namespace App\Filament\Resources\Imports;

use App\Domain\Editorial\ImportTemplates;
use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\Imports\Pages\CreateImport;
use App\Filament\Resources\Imports\Pages\ListImports;
use App\Filament\Resources\Imports\Pages\ViewImport;
use App\Filament\Resources\Imports\RelationManagers\RowsRelationManager;
use App\Models\Import;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
            Select::make('kind')->label('What are you importing?')->options(ImportTemplates::KINDS)->default('scholarships')->required()->native(false)
                ->helperText('Import universities before their degree programmes, because each programme must name a university that already exists.'),
            FileUpload::make('file')->label('Excel (.xlsx) or CSV file')->required()->disk('local')->directory('imports')->visibility('private')
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(5120)
                ->helperText('Start from a template (button "Download a template" on the Imports page): the first sheet is the one that is read. Use official sources, ISO dates, and no student data. Nothing is published by an import.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->dateTime('j M Y H:i')->sortable(),
            TextColumn::make('kind')->label('Kind')->formatStateUsing(fn ($state) => ImportTemplates::KINDS[$state] ?? $state)->badge()->color('gray'),
            TextColumn::make('filename'),
            TextColumn::make('status')->badge()->color(fn ($s) => match ($s) {
                'committed' => 'success', 'validated' => 'info', 'failed' => 'danger', default => 'gray'
            }),
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
