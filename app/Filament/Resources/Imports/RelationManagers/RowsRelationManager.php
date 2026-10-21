<?php

namespace App\Filament\Resources\Imports\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Row-level preview with row numbers, proposed action and errors (IMP-02). */
class RowsRelationManager extends RelationManager
{
    protected static string $relationship = 'rows';

    protected static ?string $title = 'Rows';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('row_number')->label('Row')->sortable(),
            TextColumn::make('status')->badge()->color(fn ($s) => $s === 'valid' ? 'success' : 'danger'),
            TextColumn::make('action')->badge()->placeholder('-'),
            TextColumn::make('payload.programme_title')->label('Programme')->limit(40),
            TextColumn::make('payload.cycle_year')->label('Year'),
            TextColumn::make('errors')->formatStateUsing(fn ($state) => is_array($state) ? implode(' | ', $state) : $state)->wrap()->color('danger'),
            IconColumn::make('applied')->boolean(),
        ])->filters([SelectFilter::make('status')->options(['valid' => 'Valid', 'invalid' => 'Invalid'])])->defaultSort('row_number')->paginated([25, 50, 100]);
    }
}
