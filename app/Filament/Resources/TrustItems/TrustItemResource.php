<?php

namespace App\Filament\Resources\TrustItems;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\TrustItems\Pages\ManageTrustItems;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\SafeUrl;
use App\Models\TrustItem;
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
use Filament\Tables\Table;

/** The homepage trust strip: factual promises and real partner or accreditation logos. Do not list a partner that has not agreed. */
class TrustItemResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = TrustItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'trust item';

    protected static ?string $navigationLabel = 'Trust section';

    protected static function permission(): string
    {
        return 'site.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')->options(TrustItem::KINDS)->required()->default('point')->live(),
            TextInput::make('title')->required()->maxLength(120)->helperText('For a logo: the organisation name.'),
            TextInput::make('text')->maxLength(400)->columnSpanFull()->visible(fn ($get) => $get('kind') !== 'partner')
                ->helperText('Only statements that are true today. Never promise selection, admission or visa outcomes.'),
            TextInput::make('url')->label('Link (optional)')->rules(SafeUrl::rules())->maxLength(500)->columnSpanFull(),
            MediaPicker::make('image_path')->label('Logo')->columnSpanFull()->visible(fn ($get) => $get('kind') === 'partner'),
            Toggle::make('enabled')->label('Show on the homepage')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')->badge()->formatStateUsing(fn ($s) => TrustItem::KINDS[$s] ?? $s),
                TextColumn::make('title')->searchable(),
                TextColumn::make('text')->limit(60)->wrap(),
                ToggleColumn::make('enabled')->label('Visible')->disabled(fn () => ! static::allowed()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('sort')->reorderable('sort');
    }

    public static function getPages(): array
    {
        return ['index' => ManageTrustItems::route('/')];
    }
}
