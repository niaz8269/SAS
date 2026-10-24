<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Domain\Admin\EnquiryConversion;
use App\Domain\Editorial\AuditLog;
use App\Enums\EnquiryStatus;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\ServiceCases\ServiceCaseResource;
use App\Models\Enquiry;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EditEnquiry extends EditRecord
{
    protected static string $resource = EnquiryResource::class;

    /** status / owner are guarded against mass assignment, so they are applied explicitly after authorising. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = auth()->user();
        abort_unless($user->can('update', $record), 403);

        $before = $record->only(['status', 'owner_id']);
        $owner = $data['owner_id'] ?? null;
        if ((int) $owner !== (int) $record->owner_id) {
            abort_unless($user->can('enquiries.assign'), 403); // only managers may (re)assign
        }

        $record->forceFill([
            'status' => $data['status'],
            'owner_id' => $owner ?: null,
            'next_action' => $data['next_action'] ?? null,
            'next_action_at' => $data['next_action_at'] ?? null,
        ]);
        if ($record->isDirty('owner_id') && $record->owner_id && $record->status === EnquiryStatus::New) {
            $record->status = EnquiryStatus::Assigned;
        }
        $record->save();

        AuditLog::record('enquiry.updated', $record, $before, $record->only(['status', 'owner_id']));

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('convert')->label('Turn into a case')->icon('heroicon-o-briefcase')->color('success')
                ->visible(fn (Enquiry $record) => $record->converted_case_id === null && auth()->user()->can('cases.manage'))
                ->modalHeading('Open a case for this person')
                ->modalDescription(fn (Enquiry $record) => app(EnquiryConversion::class)->studentFor($record)
                    ? 'A case is opened for '.app(EnquiryConversion::class)->studentFor($record)->name.' and the US$'.config('sas.fees.application').' application fee is issued. The enquiry is marked converted.'
                    : 'Nobody with the email '.$record->email.' has a SAS account yet, so a case cannot be opened. Ask them to register first.')
                ->fillForm(fn (Enquiry $record) => ['cycle_id' => $record->cycle_id, 'advisor_id' => $record->owner_id])
                ->schema([
                    Select::make('cycle_id')->label('Scholarship call')->searchable()->placeholder('Not linked to a listing')
                        ->options(fn () => ScholarshipCycle::with('programme')->orderByDesc('id')->limit(300)->get()->mapWithKeys(fn (ScholarshipCycle $c) => [$c->id => $c->programme->title.' ('.$c->intake_year.')'])->all()),
                    Select::make('advisor_id')->label('Counsellor')->searchable()->placeholder('The least busy counsellor')->options(fn () => User::counsellors()->pluck('name', 'id')->all()),
                    Textarea::make('note')->label('Note for the case (staff only)')->rows(3)->maxLength(1000),
                ])
                ->action(function (Enquiry $record, array $data) {
                    try {
                        $case = app(EnquiryConversion::class)->convert($record, ($data['cycle_id'] ?? null) ? ScholarshipCycle::find($data['cycle_id']) : null, ($data['advisor_id'] ?? null) ? User::find($data['advisor_id']) : null, $data['note'] ?? null, auth()->user());
                        Notification::make()->title('Case opened')->body($case->title)->success()->send();
                        $this->redirect(EnquiryResource::getUrl('edit', ['record' => $record]));
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('openCase')->label('Open the case')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->visible(fn (Enquiry $record) => $record->converted_case_id !== null && auth()->user()->can('cases.manage'))
                ->url(fn (Enquiry $record) => ServiceCaseResource::getUrl('edit', ['record' => $record->converted_case_id])),
        ];
    }
}
