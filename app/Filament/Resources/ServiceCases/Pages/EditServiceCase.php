<?php

namespace App\Filament\Resources\ServiceCases\Pages;

use App\Domain\Cases\CaseService;
use App\Domain\Documents\Requirements;
use App\Domain\Editorial\AuditLog;
use App\Filament\Resources\ServiceCases\ServiceCaseResource;
use App\Models\Audit;
use App\Models\ServiceCase;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EditServiceCase extends EditRecord
{
    protected static string $resource = ServiceCaseResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor->can('update', $record), 403);

        $record->fill(['title' => $data['title'] ?? $record->title, 'internal_note' => $data['internal_note'] ?? null])->save();
        if ((int) ($data['degree_level_id'] ?? 0) !== (int) $record->degree_level_id) {
            app(CaseService::class)->setDegreeLevel($record, ($data['degree_level_id'] ?? null) ?: null, $actor);
        }
        $advisor = $data['advisor_id'] ?? null;
        if ($actor->can('cases.manage') && (int) $advisor !== (int) $record->advisor_id) {
            app(CaseService::class)->assign($record, $advisor ? User::find($advisor) : null, $actor);
        }

        return $record;
    }

    private function logFormView(ServiceCase $record): void
    {
        $recent = Audit::where('action', 'case.form_viewed')->where('auditable_id', $record->id)->where('auditable_type', $record->getMorphClass())
            ->where('actor_id', auth()->id())->where('created_at', '>', now()->subMinutes(15))->exists();
        $recent || AuditLog::record('case.form_viewed', $record, null, null, null, auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('applicationForm')->label('Application form')->icon('heroicon-o-clipboard-document-list')
                ->visible(fn (ServiceCase $record) => $record->form_shared_at !== null && auth()->user()->can('view', $record))
                ->modalHeading('Application form')->modalSubmitAction(false)->modalCancelActionLabel('Close')->modalWidth('4xl')
                ->modalContent(function (ServiceCase $record) {
                    $this->logFormView($record); // the modal re-renders on every interaction, so a view is logged once per 15 minutes

                    return view('filament.case-application-form', ['case' => $record, 'form' => $record->sharedForm()]);
                }),
            Action::make('documentChecklist')->label('Document checklist')->icon('heroicon-o-document-check')
                ->visible(fn (ServiceCase $record) => auth()->user()->can('view', $record))
                ->modalHeading('Document checklist')->modalSubmitAction(false)->modalCancelActionLabel('Close')->modalWidth('3xl')
                ->modalContent(function (ServiceCase $record) {
                    $rows = app(Requirements::class)->forCase($record->loadMissing('cycle'), $record->shares()->pluck('document_id')->all());

                    return view('filament.case-document-checklist', ['case' => $record->loadMissing('degreeLevel'), 'rows' => $rows, 'progress' => app(Requirements::class)->progress($rows)]);
                }),
            Action::make('changeStatus')->label('Change status')->icon('heroicon-o-arrow-path')
                ->visible(fn (ServiceCase $record) => (ServiceCase::NEXT[$record->status] ?? []) !== [] && auth()->user()->can('update', $record))
                ->schema(fn (ServiceCase $record) => [
                    Select::make('status')->label('Move to')->required()
                        ->options(collect(ServiceCase::NEXT[$record->status] ?? [])->mapWithKeys(fn ($s) => [$s => ServiceCase::STATUSES[$s]])->all())
                        ->helperText('Accepting issues the agency service charge. Rejecting refunds part of a paid application fee. Withdrawing follows the cancellation policy.'),
                ])
                ->action(function (ServiceCase $record, array $data) {
                    try {
                        app(CaseService::class)->setStatus($record, $data['status'], auth()->user());
                        Notification::make()->title('Status changed to '.ServiceCase::STATUSES[$data['status']])->success()->send();
                        $this->refreshFormData(['status']);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
