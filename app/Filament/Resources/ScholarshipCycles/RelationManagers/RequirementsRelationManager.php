<?php

namespace App\Filament\Resources\ScholarshipCycles\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Changing these makes students with a plan see a "Requirements updated" prompt (APP-02). */
class RequirementsRelationManager extends CycleRelationManager
{
    protected static string $relationship = 'requirements';

    protected static ?string $title = 'Required documents';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('document_type')->options(config('sas.document_types'))->default('other'),
            Textarea::make('text')->required()->rows(2),
            TextInput::make('conditional_rule')->label('Condition (if it only applies sometimes)'),
            Toggle::make('mandatory')->default(true),
            TextInput::make('sort')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->reorderable('sort')->defaultSort('sort')->columns([
            TextColumn::make('text')->wrap()->limit(90), TextColumn::make('document_type')->badge(), IconColumn::make('mandatory')->boolean(),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
