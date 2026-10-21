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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** SCHEMA-03: only criteria that can be structured safely and have evidence. Everything else stays as text in the eligibility notes. */
class CriteriaRelationManager extends CycleRelationManager
{
    protected static string $relationship = 'criteria';

    protected static ?string $title = 'Structured criteria (used for matching)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->options(['min_gpa' => 'Minimum grade', 'language' => 'Language test', 'age' => 'Age'])->required(),
            Select::make('operator')->options(['>=' => '>= (at least)', '<=' => '<= (at most)', '=' => '= (exactly)'])->required()->default('>='),
            TextInput::make('value')->required()->maxLength(32)->helperText('A number, e.g. 6.5 or 3.0 or 35.'),
            TextInput::make('unit')->label('Test name or grading scale')->helperText('IELTS, TOEFL... or the exact scale such as "CGPA /4.0". Grades are never converted between scales.'),
            Toggle::make('mandatory')->default(true),
            Textarea::make('evidence')->label('Where the source states this')->required()->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('type')->badge(), TextColumn::make('operator'), TextColumn::make('value'), TextColumn::make('unit'), TextColumn::make('evidence')->limit(50),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
