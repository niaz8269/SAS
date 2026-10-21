<?php

namespace App\Filament\Resources\ScholarshipCycles\Pages;

use App\Domain\Editorial\AuditLog;
use App\Domain\Editorial\CycleWorkflow;
use App\Domain\Scholarship\DeadlineService;
use App\Enums\ApplicationStatus;
use App\Enums\PublicationState as S;
use App\Filament\Resources\ScholarshipCycles\ScholarshipCycleResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Workflow buttons call the same CycleWorkflow actions as everything else; the UI never decides a transition itself. */
class EditScholarshipCycle extends EditRecord
{
    protected static string $resource = ScholarshipCycleResource::class;

    protected function getHeaderActions(): array
    {
        $wf = app(CycleWorkflow::class);
        $user = fn () => auth()->user();
        $state = fn (): S => $this->getRecord()->publication_state;

        $run = function (callable $fn, string $success): void {
            try {
                $fn();
                $this->getRecord()->refresh();
                $this->refreshFormData(['publication_state']);
                Notification::make()->title($success)->success()->send();
            } catch (ValidationException $e) {
                Notification::make()->title('Cannot continue')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();
            } catch (AuthorizationException $e) {
                Notification::make()->title('Not allowed')->body($e->getMessage())->danger()->send();
            }
        };

        return [
            Action::make('state')->label(fn () => 'State: '.$state()->label())->color('gray')->disabled()->badge(),
            Action::make('submit')->label('Submit for review')->icon('heroicon-o-paper-airplane')
                ->visible(fn () => in_array($state(), [S::Draft, S::ChangesRequested], true) && $user()->can('content.draft'))
                ->requiresConfirmation()->modalDescription('At least one source (URL, publisher, checked date) must be recorded first.')
                ->action(fn () => $run(fn () => $wf->submitForReview($this->getRecord(), $user()), 'Submitted for review')),
            Action::make('approve')->label('Approve')->color('success')->icon('heroicon-o-check')
                ->visible(fn () => $state() === S::InReview && ($user()->can('content.review') || $user()->can('content.override')))
                ->schema([
                    Textarea::make('notes')->label('Review notes'),
                    Textarea::make('override_reason')->label('Override reason')->helperText('Only for administrators approving their own change. Recorded in the audit log.'),
                ])
                ->action(fn (array $data) => $run(fn () => $wf->approve($this->getRecord(), $user(), $data['notes'] ?? null, $data['override_reason'] ?? null), 'Approved')),
            Action::make('changes')->label('Request changes')->color('warning')->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn () => $state() === S::InReview && $user()->can('content.review'))
                ->schema([Textarea::make('notes')->required()->label('What needs to change?')])
                ->action(fn (array $data) => $run(fn () => $wf->requestChanges($this->getRecord(), $user(), $data['notes']), 'Changes requested')),
            Action::make('publish')->label('Publish')->color('success')->icon('heroicon-o-globe-alt')
                ->visible(fn () => $state() === S::Approved && $user()->can('content.publish'))
                ->requiresConfirmation()->modalDescription('The cycle becomes publicly visible and reminders are scheduled for people tracking it.')
                ->action(fn () => $run(fn () => $wf->publish($this->getRecord(), $user()), 'Published')),
            Action::make('archive')->label('Archive')->color('gray')->icon('heroicon-o-archive-box')
                ->visible(fn () => $state() !== S::Archived && $user()->can('content.publish'))
                ->schema([Textarea::make('reason')])->requiresConfirmation()
                ->action(fn (array $data) => $run(fn () => $wf->archive($this->getRecord(), $user(), $data['reason'] ?? null), 'Archived')),
            Action::make('suspend')->label(fn () => $this->getRecord()->application_status === ApplicationStatus::Suspended ? 'Resume applications' : 'Suspend applications')
                ->color('danger')->icon('heroicon-o-pause-circle')
                ->visible(fn () => $state() === S::Published && $user()->can('content.publish'))->requiresConfirmation()
                ->action(function () use ($user) {
                    $cycle = $this->getRecord();
                    $suspended = $cycle->application_status === ApplicationStatus::Suspended;
                    $cycle->forceFill(['application_status' => ($suspended ? ApplicationStatus::DeadlineUnconfirmed : ApplicationStatus::Suspended)->value])->save();
                    AuditLog::record($suspended ? 'cycle.resumed' : 'cycle.suspended', $cycle, null, null, null, $user());
                    if ($suspended) {
                        app(DeadlineService::class)->refresh($cycle);
                    }
                    $this->getRecord()->refresh();
                    Notification::make()->title($suspended ? 'Applications resumed' : 'Applications suspended')->success()->send();
                }),
        ];
    }

    /** Placement and verification flags need the matching permission even if a request is tampered with. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        if (! $user->can('content.publish')) {
            unset($data['featured'], $data['sponsored'], $data['sponsored_label']);
        }
        if (! $user->can('content.review')) {
            unset($data['fully_funded_verified']);
        }

        return $data;
    }
}
