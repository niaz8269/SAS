<?php

namespace App\Filament\Resources\ScholarshipCycles\RelationManagers;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Read-only publication history: who moved the cycle, when, why. */
class ReviewsRelationManager extends CycleRelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'Publication history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->dateTime('j M Y H:i'),
            TextColumn::make('reviewer.name')->label('By'),
            TextColumn::make('from_state')->badge(), TextColumn::make('to_state')->badge(),
            IconColumn::make('override')->boolean()->label('Override'),
            TextColumn::make('notes')->wrap()->limit(120),
        ])->defaultSort('id', 'desc');
    }
}
