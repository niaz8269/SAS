<?php

namespace App\Filament\Resources\MenuItems;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\MenuItems\Pages\ManageMenuItems;
use App\Filament\Support\SafeUrl;
use App\Models\MenuItem;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

/** Header and footer links. Add, rename, point anywhere, hide, reorder (drag the handle) or delete. */
class MenuItemResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = MenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'menu link';

    protected static ?string $navigationLabel = 'Menus';

    protected static function permission(): string
    {
        return 'site.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('location')->options(MenuItem::LOCATIONS)->required()->default('header'),
            TextInput::make('label')->required()->maxLength(80),
            TextInput::make('url')->label('Address')->required()->rules(SafeUrl::rules())->maxLength(500)->helperText('A path like /about, or https://…, mailto:… or tel:…'),
            Toggle::make('new_tab')->label('Open in a new tab'),
            Toggle::make('visible')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('location')->badge()->formatStateUsing(fn ($s) => MenuItem::LOCATIONS[$s] ?? $s)->sortable(),
                TextColumn::make('label')->searchable(),
                TextColumn::make('url')->label('Address')->limit(50),
                ToggleColumn::make('visible')->disabled(fn () => ! static::allowed()),
            ])
            ->filters([SelectFilter::make('location')->options(MenuItem::LOCATIONS)])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('sort')->reorderable('sort')->groups([Group::make('location')->label('Menu')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMenuItems::route('/')];
    }
}
