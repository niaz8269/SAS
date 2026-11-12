<?php

namespace App\Filament\Resources\ScholarshipCycles\RelationManagers;

use App\Models\CycleApplicationStep;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** The process as the official source describes it, in order: how to apply, how applicants are selected, what follows selection. */
class ApplicationStepsRelationManager extends CycleRelationManager
{
    protected static string $relationship = 'applicationSteps';

    protected static ?string $title = 'Application process';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('phase')->options(CycleApplicationStep::PHASES)->default('apply')->required(),
            TextInput::make('title')->required()->maxLength(200)->helperText('One short action, for example "Apply to the university first".'),
            Textarea::make('detail')->rows(3)->helperText('What the official source says about this step. Leave out anything it does not say.'),
            TextInput::make('source_url')->url()->maxLength(500)->label('Page this step comes from'),
            TextInput::make('sort')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->reorderable('sort')->defaultSort('sort')->columns([
            TextColumn::make('phase')->badge()->formatStateUsing(fn ($state) => CycleApplicationStep::PHASES[$state] ?? $state),
            TextColumn::make('title')->wrap()->limit(80),
            TextColumn::make('detail')->wrap()->limit(90),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
