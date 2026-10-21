<?php

namespace App\Filament\Resources\Universities;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\Universities\Pages\ManageUniversities;
use App\Models\University;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UniversityResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = University::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Reference data';

    protected static ?string $modelLabel = 'university';

    protected static function permission(): string
    {
        return 'taxonomy.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(190),
            TextInput::make('slug')->required()->unique(ignoreRecord: true)->alphaDash(),
            Select::make('country_id')->relationship('country', 'name')->searchable()->preload(),
            TextInput::make('city'),
            TextInput::make('website')->url(),
            TextInput::make('domain'),
            DatePicker::make('verified_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('country.name'),
                TextColumn::make('city'),
                IconColumn::make('verified_at')->label('Verified')->boolean()->getStateUsing(fn ($record) => $record->verified_at !== null),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return ['index' => ManageUniversities::route('/')];
    }
}
