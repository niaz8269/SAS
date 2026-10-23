<?php

namespace App\Filament\Resources\PartnerOrganisations\Pages;

use App\Filament\Resources\PartnerOrganisations\PartnerOrganisationResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPartnerOrganisation extends EditRecord
{
    protected static string $resource = PartnerOrganisationResource::class;

    /** status is guarded against mass assignment, so it is applied explicitly after authorising. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless(auth()->user()->can('update', $record), 403);
        $record->fill(collect($data)->except('status')->all());
        $record->forceFill(['status' => $data['status'] ?? $record->status])->save();

        return $record;
    }
}
