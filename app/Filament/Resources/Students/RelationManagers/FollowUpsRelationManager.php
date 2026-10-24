<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Models\StudentFollowUp;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The counsellors' notes on this student over time (staff only). Add one with Update status. */
class FollowUpsRelationManager extends RelationManager
{
    protected static string $relationship = 'followUps';

    protected static ?string $title = 'Status history and notes';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i'),
                TextColumn::make('author.name')->label('By')->placeholder('-'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => StudentFollowUp::STATUSES[$state] ?? $state),
                TextColumn::make('wants')->label('What the student wants')->wrap()->limit(300)->placeholder('-'),
                TextColumn::make('comment')->wrap()->limit(300)->placeholder('-'),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No notes yet')
            ->emptyStateDescription('Press Update status above to record where the student stands and what they want.');
    }
}
