<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Domain\Documents\Requirements;
use App\Domain\Scholarship\DeadlineService;
use App\Domain\StudentWorkspace\ApplicationReview;
use App\Models\ApplicationPlan;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** The student's applications (drafts included) with deadline, documents and next step; the counsellor can send one to SAS review, or return or pass one that is waiting. */
class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'applicationPlans';

    protected static ?string $title = 'Applications';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->counsels($ownerRecord);
    }

    /** Not read only: the buttons on this tab are the point of it (read access is decided by the policies). */
    public function isReadOnly(): bool
    {
        return false;
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

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['cycle.programme', 'degreeLevel']))
            ->columns([
                TextColumn::make('application_no')->label('Application')->searchable(),
                TextColumn::make('cycle.programme.title')->label('Scholarship')->wrap()->limit(60),
                TextColumn::make('degreeLevel.name')->label('Degree')->placeholder('Not chosen'),
                TextColumn::make('stage')->badge()->formatStateUsing(fn ($state) => ApplicationPlan::STAGES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'passed' => 'success', 'returned' => 'danger', 'submitted' => 'warning', default => 'gray'
                    }),
                TextColumn::make('deadline')->label('Closes')->state(fn (ApplicationPlan $r) => app(DeadlineService::class)->nextClosing($r->cycle)?->format('j M Y'))->placeholder('Not confirmed'),
                TextColumn::make('documents_ready')->label('Documents')->state(function (ApplicationPlan $r) {
                    $p = app(Requirements::class)->progress(app(Requirements::class)->forPlan($r));

                    return $p['required'] === 0 ? 'None mandatory' : $p['ready'].' of '.$p['required'];
                }),
                TextColumn::make('next_step')->label('Next step')->wrap()->state(fn (ApplicationPlan $r) => match ($r->stage) {
                    'draft' => app(ApplicationReview::class)->problems($r) ? 'Finish the form and documents' : 'Ready to send to SAS',
                    'returned' => 'Fix: '.str((string) $r->review_remarks)->limit(60),
                    'submitted' => 'Waiting for SAS review',
                    default => 'Review passed',
                }),
                TextColumn::make('updated_at')->label('Last change')->since(),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No applications yet')
            ->recordActions([
                Action::make('send')->label('Send to SAS review')->icon('heroicon-o-paper-airplane')->color('success')
                    ->visible(fn (ApplicationPlan $record) => auth()->user()->can('submitForReview', $record))
                    ->requiresConfirmation()->modalDescription("The application goes to SAS review on the student's behalf. It can only be sent when the form and mandatory documents are complete, and it is locked until SAS decides.")
                    ->action(function (ApplicationPlan $record) {
                        try {
                            app(ApplicationReview::class)->submit($record, auth()->user());
                            Notification::make()->title('Sent to SAS review')->success()->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title('Not ready yet')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('return')->label('Return')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                    ->visible(fn (ApplicationPlan $record) => auth()->user()->can('review', $record))
                    ->schema([Textarea::make('remarks')->label('What the student must fix')->required()->rows(4)->maxLength(2000)])
                    ->action(fn (ApplicationPlan $record, array $data) => static::decide($record, 'return', $data['remarks'])),
                Action::make('pass')->label('Pass')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (ApplicationPlan $record) => auth()->user()->can('review', $record))
                    ->requiresConfirmation()->modalDescription('The student (and agent) are told the application passed review.')
                    ->schema([Textarea::make('remarks')->label('Note for the student (optional)')->rows(3)->maxLength(2000)])
                    ->action(fn (ApplicationPlan $record, array $data) => static::decide($record, 'pass', $data['remarks'] ?? null)),
                Action::make('pdf')->label('SAS form (PDF)')->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (ApplicationPlan $record) => auth()->user()->can('view', $record))
                    ->url(fn (ApplicationPlan $record) => route('review.download', $record->public_id), shouldOpenInNewTab: true),
                Action::make('print')->label('Printable')->icon('heroicon-o-printer')
                    ->visible(fn (ApplicationPlan $record) => auth()->user()->can('view', $record))
                    ->url(fn (ApplicationPlan $record) => route('review.preview', $record->public_id), shouldOpenInNewTab: true),
            ]);
    }
}
