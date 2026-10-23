<?php

namespace App\Filament\Resources\ServiceCases\Pages;

use App\Domain\Cases\CaseService;
use App\Filament\Resources\ServiceCases\ServiceCaseResource;
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
        $advisor = $data['advisor_id'] ?? null;
        if ($actor->can('cases.manage') && (int) $advisor !== (int) $record->advisor_id) {
            app(CaseService::class)->assign($record, $advisor ? User::find($advisor) : null, $actor);
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
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
