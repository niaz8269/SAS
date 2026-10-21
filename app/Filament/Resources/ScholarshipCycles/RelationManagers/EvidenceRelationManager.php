<?php

namespace App\Filament\Resources\ScholarshipCycles\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** DATA-02: official source URL, publisher, checked timestamp and evidence note. Aggregators are leads only. */
class EvidenceRelationManager extends CycleRelationManager
{
    protected static string $relationship = 'evidence';

    protected static ?string $title = 'Sources and evidence';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('url')->url()->required()->maxLength(2000)->label('Official source URL'),
            TextInput::make('publisher')->required(),
            Select::make('publisher_type')->options(['funder' => 'Funder', 'government' => 'Government', 'embassy' => 'Embassy', 'university' => 'University', 'aggregator' => 'Aggregator (lead only)'])->required()->default('funder'),
            DateTimePicker::make('checked_at')->required()->default(now())->maxDate(now()->addMinute()),
            Textarea::make('note')->label('Evidence note')->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('publisher')->searchable(),
            TextColumn::make('publisher_type')->badge()->color(fn ($state) => $state === 'aggregator' ? 'warning' : 'success'),
            TextColumn::make('url')->limit(50)->url(fn ($record) => $record->url, shouldOpenInNewTab: true),
            TextColumn::make('checked_at')->dateTime('j M Y H:i'),
        ])->headerActions([CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['created_by' => auth()->id()])])
            ->recordActions([EditAction::make(), DeleteAction::make()])->defaultSort('checked_at', 'desc');
    }
}
