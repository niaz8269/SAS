<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Domain\Editorial\AuditLog;
use App\Enums\EnquiryStatus;
use App\Filament\Resources\Enquiries\EnquiryResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

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
        return [];
    }
}
