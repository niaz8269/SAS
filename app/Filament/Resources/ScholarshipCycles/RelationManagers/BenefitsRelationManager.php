<?php

namespace App\Filament\Resources\ScholarshipCycles\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BenefitsRelationManager extends CycleRelationManager
{
    protected static string $relationship = 'benefits';

    protected static ?string $title = 'Funding components';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->options(['tuition' => 'Tuition', 'living_allowance' => 'Living allowance', 'accommodation' => 'Accommodation', 'travel' => 'Travel', 'insurance' => 'Insurance', 'other' => 'Other'])->required(),
            Select::make('coverage')->options(['covered' => 'Covered', 'partial' => 'Partly covered', 'not_covered' => 'Not covered', 'unknown' => 'Not confirmed'])->required()->default('unknown'),
            TextInput::make('limit_text')->label('Limit (as published)'),
            Textarea::make('conditions')->rows(2),
            TextInput::make('sort')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('type')->badge(), TextColumn::make('coverage')->badge(), TextColumn::make('limit_text'), TextColumn::make('conditions')->limit(50),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
