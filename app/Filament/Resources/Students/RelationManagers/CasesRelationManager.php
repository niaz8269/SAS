<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Resources\ServiceCases\ServiceCaseResource;
use App\Models\ServiceCase;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The applications SAS supports for this student (cases), with a link to each one the staff member may open. */
class CasesRelationManager extends RelationManager
{
    protected static string $relationship = 'cases';

    protected static ?string $title = 'SAS cases';

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
            ->modifyQueryUsing(fn ($query) => $query->with('advisor'))
            ->columns([
                TextColumn::make('title')->wrap()->limit(60),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => ServiceCase::STATUSES[$state] ?? $state),
                TextColumn::make('advisor.name')->label('Case counsellor')->placeholder('Unassigned'),
                TextColumn::make('opened_at')->date('j M Y'),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No SAS cases yet')
            ->recordActions([
                Action::make('open')->label('Open case')->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (ServiceCase $record) => auth()->user()->can('view', $record))
                    ->url(fn (ServiceCase $record) => ServiceCaseResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
