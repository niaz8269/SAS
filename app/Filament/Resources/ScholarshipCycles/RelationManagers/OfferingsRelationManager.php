<?php

namespace App\Filament\Resources\ScholarshipCycles\RelationManagers;

use App\Filament\Support\SafeUrl;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Degree levels and the degree programmes a scholarship offers. Leave the programme name empty for the summary line of a level. */
class OfferingsRelationManager extends CycleRelationManager
{
    protected static string $relationship = 'offerings';

    protected static ?string $title = 'Degrees and programmes offered';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('degree_level_id')->label('Degree level')->relationship('degreeLevel', 'name')->required(),
            TextInput::make('name')->label('Programme or major')->maxLength(200)->helperText('Leave empty for the summary line of the level (for example "Any discipline offered by the host universities").'),
            TextInput::make('institution')->maxLength(200),
            Textarea::make('detail')->label('Details')->rows(3)->maxLength(2000)->helperText('Duration, teaching language, field limits. Only what the official source says.')->columnSpanFull(),
            TextInput::make('source_text')->label('Exactly as published on the source')->maxLength(600)->columnSpanFull(),
            TextInput::make('source_url')->label('Source page')->url()->rules(SafeUrl::rules())->maxLength(500)->columnSpanFull(),
            TextInput::make('sort')->numeric()->default(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table->reorderable('sort')->defaultSort('sort')->columns([
            TextColumn::make('degreeLevel.name')->label('Level')->badge(),
            TextColumn::make('name')->label('Programme or major')->placeholder('Summary for the level')->wrap(),
            TextColumn::make('institution')->placeholder('—'),
            TextColumn::make('detail')->limit(60),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
