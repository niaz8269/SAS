<?php

namespace App\Filament\Resources\ApplicationReviews;

use App\Domain\Documents\Requirements;
use App\Domain\StudentWorkspace\ApplicationReview;
use App\Filament\Resources\ApplicationReviews\Pages\ListApplicationReviews;
use App\Models\ApplicationPlan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Applications that students or agents sent to SAS for review. Staff read the form and documents, then return the
 * application with remarks to fix, or pass it. Access follows ApplicationPlanPolicy.
 */
class ApplicationReviewResource extends Resource
{
    protected static ?string $model = ApplicationPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'application';

    protected static ?string $navigationLabel = 'Applications to review';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('stage', '!=', 'draft')->with(['user.managedBy', 'cycle.programme', 'degreeLevel']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('application_no')->label('Application')->searchable()->sortable(),
                TextColumn::make('user.name')->label('Student')->searchable(),
                TextColumn::make('cycle.programme.title')->label('Scholarship')->wrap()->limit(60),
                TextColumn::make('user.managedBy.name')->label('Agent')->placeholder('Direct student'),
                TextColumn::make('stage')->badge()->formatStateUsing(fn ($state) => ApplicationPlan::STAGES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'passed' => 'success', 'returned' => 'danger', 'submitted' => 'warning', default => 'gray'
                    }),
                TextColumn::make('review_requested_at')->label('Sent')->dateTime('j M Y, H:i')->sortable(),
            ])
            ->filters([SelectFilter::make('stage')->options(collect(ApplicationPlan::STAGES)->except('draft')->all())->default('submitted')])
            ->recordActions([
                Action::make('details')->label('Open')->icon('heroicon-o-eye')
                    ->modalHeading(fn (ApplicationPlan $record) => $record->application_no.': '.$record->user->name)->modalSubmitAction(false)->modalCancelActionLabel('Close')->modalWidth('4xl')
                    ->modalContent(function (ApplicationPlan $record) {
                        $rows = app(Requirements::class)->forPlan($record);

                        return view('filament.application-review', ['plan' => $record, 'rows' => $rows, 'progress' => app(Requirements::class)->progress($rows)]);
                    }),
                Action::make('return')->label('Return')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                    ->visible(fn (ApplicationPlan $record) => auth()->user()->can('review', $record))
                    ->schema([Textarea::make('remarks')->label('What the student or agent must fix')->required()->rows(5)->maxLength(2000)])
                    ->action(fn (ApplicationPlan $record, array $data) => static::decide($record, 'return', $data['remarks'])),
                Action::make('pass')->label('Pass')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (ApplicationPlan $record) => auth()->user()->can('review', $record))
                    ->requiresConfirmation()->modalDescription('The student (and agent) are told the application passed review.')
                    ->schema([Textarea::make('remarks')->label('Note for the student (optional)')->rows(3)->maxLength(2000)])
                    ->action(fn (ApplicationPlan $record, array $data) => static::decide($record, 'pass', $data['remarks'] ?? null)),
            ])
            ->defaultSort('review_requested_at', 'desc');
    }

    private static function decide(ApplicationPlan $plan, string $decision, ?string $remarks): void
    {
        abort_unless(auth()->user()->can('review', $plan), 403);
        try {
            app(ApplicationReview::class)->review($plan, $decision, $remarks, auth()->user());
            Notification::make()->title($decision === 'return' ? 'Returned with your remarks' : 'Passed')->success()->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => ListApplicationReviews::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
