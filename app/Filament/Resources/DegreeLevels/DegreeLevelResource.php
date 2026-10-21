<?php

namespace App\Filament\Resources\DegreeLevels;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\DegreeLevels\Pages\ManageDegreeLevels;
use App\Models\DegreeLevel;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DegreeLevelResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = DegreeLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3BottomLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Reference data';

    protected static ?string $modelLabel = 'degree level';

    protected static function permission(): string
    {
        return 'taxonomy.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('name')->required(),
            TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('sort')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('code'),
                TextColumn::make('sort')->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return ['index' => ManageDegreeLevels::route('/')];
    }
}
