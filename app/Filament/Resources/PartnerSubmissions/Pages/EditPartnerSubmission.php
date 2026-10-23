<?php

namespace App\Filament\Resources\PartnerSubmissions\Pages;

use App\Domain\Partners\PartnerSubmissionService;
use App\Filament\Resources\PartnerSubmissions\PartnerSubmissionResource;
use App\Models\PartnerSubmission;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EditPartnerSubmission extends EditRecord
{
    protected static string $resource = PartnerSubmissionResource::class;

    /** The two note fields are guarded against mass assignment, so they are applied explicitly after authorising. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless(auth()->user()->can('update', $record), 403);
        $record->forceFill(['editor_note' => $data['editor_note'] ?? null, 'internal_note' => $data['internal_note'] ?? null])->save();

        return $record;
    }

    protected function getHeaderActions(): array
    {
        $decide = function (string $status, string $done) {
            return function () use ($status, $done) {
                try {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    app(PartnerSubmissionService::class)->decide($this->record, $status, $this->record->editor_note, auth()->user());
                    Notification::make()->title($done)->success()->send();
                    $this->refreshFormData(['status']);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            };
        };

        return [
            Action::make('inReview')->label('Mark as being checked')->color('gray')->visible(fn (PartnerSubmission $r) => $r->status === 'submitted')->action($decide('in_review', 'Marked as being checked')),
            Action::make('needsChanges')->label('Ask for changes')->color('warning')->visible(fn (PartnerSubmission $r) => in_array($r->status, ['submitted', 'in_review'], true))
                ->requiresConfirmation()->modalDescription('Write what to change in "Message to the partner" first. The partner sees it and can edit and resend.')->action($decide('needs_changes', 'The partner was asked for changes')),
            Action::make('reject')->label('Not accepted')->color('danger')->visible(fn (PartnerSubmission $r) => in_array($r->status, ['submitted', 'in_review', 'needs_changes'], true))
                ->requiresConfirmation()->action($decide('rejected', 'Marked as not accepted')),
            Action::make('createDraft')->label('Accept and create the draft listing')->color('success')->icon('heroicon-o-document-plus')
                ->visible(fn (PartnerSubmission $r) => ! $r->cycle_id && in_array($r->status, ['submitted', 'in_review', 'needs_changes'], true))
                ->requiresConfirmation()->modalDescription('This creates a draft in Scholarship calls. It is not published: it still needs its official source checked and a second person to review it.')
                ->action(function () {
                    try {
                        $cycle = app(PartnerSubmissionService::class)->createDraft($this->record, auth()->user());
                        Notification::make()->title('Draft created in Scholarship calls (#'.$cycle->id.')')->success()->send();
                        $this->refreshFormData(['status']);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('Could not create the draft')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
