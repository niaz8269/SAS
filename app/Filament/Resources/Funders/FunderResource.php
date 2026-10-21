<?php

namespace App\Filament\Resources\Funders;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\Funders\Pages\ManageFunders;
use App\Models\Funder;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FunderResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = Funder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'Reference data';

    protected static ?string $modelLabel = 'funder';

    protected static function permission(): string
    {
        return 'taxonomy.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(190),
            TextInput::make('slug')->required()->unique(ignoreRecord: true)->alphaDash(),
            Select::make('type')->options(['government' => 'Government', 'university' => 'University', 'foundation' => 'Foundation', 'organisation' => 'Organisation', 'other' => 'Other'])->required()->default('other'),
            Select::make('country_id')->relationship('country', 'name')->searchable()->preload(),
            TextInput::make('website')->url(),
            TextInput::make('domain'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('country.name'),
                TextColumn::make('website')->limit(40),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return ['index' => ManageFunders::route('/')];
    }
}
