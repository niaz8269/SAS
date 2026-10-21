<?php

namespace App\Filament\Resources\ScholarshipProgrammes;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\ScholarshipProgrammes\Pages\ManageScholarshipProgrammes;
use App\Models\ScholarshipProgramme;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** The enduring programme. Annual calls live in Scholarship cycles (DATA-01). */
class ScholarshipProgrammeResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = ScholarshipProgramme::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Editorial';

    protected static ?int $navigationSort = 1;

    protected static function permission(): string
    {
        return 'content.draft';
    }

    public static function canDelete(Model $record): bool
    {
        return false; // restricted while cycles exist; archive cycles instead (SCHEMA-06)
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(190)->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ?: $set('slug', Str::slug($state))),
            TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
            Select::make('funder_id')->relationship('funder', 'name')->searchable()->preload()
                ->createOptionForm([TextInput::make('name')->required(), TextInput::make('slug')->required()->alphaDash()]),
            Select::make('host_country_id')->label('Primary host country')->relationship('hostCountry', 'name')->searchable()->preload(),
            Select::make('universities')->relationship('universities', 'name')->multiple()->searchable()->preload(),
            Textarea::make('summary')->rows(3)->columnSpanFull(),
            RichEditor::make('description')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->sortable(),
            TextColumn::make('funder.name'),
            TextColumn::make('hostCountry.name')->label('Host'),
            TextColumn::make('cycles_count')->counts('cycles')->label('Cycles'),
        ])->recordActions([EditAction::make()])->defaultSort('title');
    }

    public static function getPages(): array
    {
        return ['index' => ManageScholarshipProgrammes::route('/')];
    }
}
