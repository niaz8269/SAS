<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Models\ApplicationPlan;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The student's applications (drafts included), read only, with the SAS application form as a PDF. */
class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'applicationPlans';

    protected static ?string $title = 'Applications';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->counsels($ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['cycle.programme', 'degreeLevel']))
            ->columns([
                TextColumn::make('application_no')->label('Application')->searchable(),
                TextColumn::make('cycle.programme.title')->label('Scholarship')->wrap()->limit(60),
                TextColumn::make('degreeLevel.name')->label('Degree')->placeholder('Not chosen'),
                TextColumn::make('study_language')->label('Language')->placeholder('-'),
                TextColumn::make('stage')->badge()->formatStateUsing(fn ($state) => ApplicationPlan::STAGES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'passed' => 'success', 'returned' => 'danger', 'submitted' => 'warning', default => 'gray'
                    }),
                TextColumn::make('updated_at')->label('Last change')->since(),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No applications yet')
            ->recordActions([
                Action::make('pdf')->label('SAS form (PDF)')->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (ApplicationPlan $record) => auth()->user()->can('view', $record))
                    ->url(fn (ApplicationPlan $record) => route('review.download', $record->public_id), shouldOpenInNewTab: true),
                Action::make('print')->label('Printable')->icon('heroicon-o-printer')
                    ->visible(fn (ApplicationPlan $record) => auth()->user()->can('view', $record))
                    ->url(fn (ApplicationPlan $record) => route('review.preview', $record->public_id), shouldOpenInNewTab: true),
            ]);
    }
}
