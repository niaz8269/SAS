<?php

namespace App\Filament\Resources\PartnerOrganisations;

use App\Filament\Resources\PartnerOrganisations\Pages\CreatePartnerOrganisation;
use App\Filament\Resources\PartnerOrganisations\Pages\EditPartnerOrganisation;
use App\Filament\Resources\PartnerOrganisations\Pages\ListPartnerOrganisations;
use App\Filament\Resources\PartnerOrganisations\RelationManagers\UsersRelationManager;
use App\Filament\Support\SafeUrl;
use App\Models\Country;
use App\Models\PartnerOrganisation;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Organisations (universities, funders, agents) that send scholarship information. Their people sign in at /partner. */
class PartnerOrganisationResource extends Resource
{
    protected static ?string $model = PartnerOrganisation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Partners';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'partner organisation';

    protected static ?string $navigationLabel = 'Partner organisations';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(200),
            Select::make('kind')->options(PartnerOrganisation::KINDS)->required()->default('university'),
            Select::make('country_id')->label('Country')->options(fn () => Country::orderBy('name')->pluck('name', 'id')->all())->searchable()->placeholder('Not set'),
            TextInput::make('website')->maxLength(300)->rules(SafeUrl::rules()),
            TextInput::make('contact_email')->email()->maxLength(190),
            Select::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended (partners cannot sign in to the area)'])->required()->default('active'),
            Textarea::make('internal_note')->label('Internal note (staff only)')->rows(3)->maxLength(2000)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('kind')->badge()->formatStateUsing(fn ($state) => PartnerOrganisation::KINDS[$state] ?? $state),
            TextColumn::make('country.name')->placeholder('-'),
            TextColumn::make('users_count')->counts('users')->label('People'),
            TextColumn::make('submissions_count')->counts('submissions')->label('Submissions'),
            TextColumn::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
        ])->recordActions([EditAction::make()])->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [UsersRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListPartnerOrganisations::route('/'), 'create' => CreatePartnerOrganisation::route('/create'), 'edit' => EditPartnerOrganisation::route('/{record}/edit')];
    }
}
