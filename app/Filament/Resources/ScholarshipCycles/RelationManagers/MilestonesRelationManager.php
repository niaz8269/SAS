<?php

namespace App\Filament\Resources\ScholarshipCycles\RelationManagers;

use App\Filament\Support\SafeUrl;
use App\Models\CycleMilestone;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Application opening, interviews, tests, results and other dates. The closing date has its own version history under Deadlines. */
class MilestonesRelationManager extends CycleRelationManager
{
    protected static string $relationship = 'milestones';

    protected static ?string $title = 'Timeline (opening, interviews, tests, results)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')->options(CycleMilestone::KINDS)->required()->default('opens'),
            TextInput::make('label')->maxLength(120)->helperText('Optional: name the stage, for example "Round 2 interviews".'),
            Select::make('date_type')->options(CycleMilestone::DATE_TYPES)->required()->default('exact')->live(),
            DatePicker::make('starts_on')->label('Date (or start)')->visible(fn (Get $get) => ! in_array($get('date_type'), ['not_announced', 'route_dependent'], true))->required(fn (Get $get) => in_array($get('date_type'), ['exact', 'date_only', 'month', 'range'], true)),
            DatePicker::make('ends_on')->label('End date')->visible(fn (Get $get) => $get('date_type') === 'range')->required(fn (Get $get) => $get('date_type') === 'range'),
            TextInput::make('time_text')->label('Time, exactly as published')->maxLength(80)->helperText('Leave blank if the source gives no time. Never guess one.')->visible(fn (Get $get) => $get('date_type') === 'exact'),
            TextInput::make('source_text')->label('Exactly as published on the source')->maxLength(600)->columnSpanFull(),
            TextInput::make('source_url')->label('Source page')->url()->rules(SafeUrl::rules())->maxLength(500)->columnSpanFull(),
            TextInput::make('sort')->numeric()->default(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table->reorderable('sort')->defaultSort('sort')->columns([
            TextColumn::make('kind')->badge()->formatStateUsing(fn ($s) => CycleMilestone::KINDS[$s] ?? $s),
            TextColumn::make('label')->placeholder('—'),
            TextColumn::make('when')->label('When')->state(fn (CycleMilestone $r) => $r->display())->wrap(),
            TextColumn::make('source_text')->label('As published')->limit(50),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
