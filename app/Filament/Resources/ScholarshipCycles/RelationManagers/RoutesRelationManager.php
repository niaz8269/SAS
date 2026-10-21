<?php

namespace App\Filament\Resources\ScholarshipCycles\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** DIR-07: routes with different deadlines stay separate records. */
class RoutesRelationManager extends CycleRelationManager
{
    protected static string $relationship = 'routes';

    protected static ?string $title = 'Application routes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(190),
            Select::make('route_type')->options(['funder_portal' => 'Funder portal', 'university' => 'University', 'embassy' => 'Embassy', 'departmental' => 'Departmental', 'other' => 'Other'])->required(),
            Select::make('university_id')->relationship('university', 'name')->searchable()->preload(),
            TextInput::make('application_url')->url()->maxLength(2000)->helperText('Official application page only. The domain is shown to visitors.'),
            TextInput::make('fee_notes')->label('Application fee (as published)'),
            TextInput::make('sort')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'), TextColumn::make('route_type')->badge(), TextColumn::make('application_domain')->label('Domain'),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
