<?php

namespace App\Filament\Resources\PartnerOrganisations\Pages;

use App\Filament\Resources\PartnerOrganisations\PartnerOrganisationResource;
use App\Models\PartnerOrganisation;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePartnerOrganisation extends CreateRecord
{
    protected static string $resource = PartnerOrganisationResource::class;

    /** status is guarded against mass assignment, so it is applied explicitly after authorising. */
    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(auth()->user()->can('create', PartnerOrganisation::class), 403);
        $organisation = new PartnerOrganisation(collect($data)->except('status')->all());
        $organisation->forceFill(['status' => $data['status'] ?? 'active'])->save();

        return $organisation;
    }

    /** After adding an organisation, go straight to its page where the people who can sign in are added. */
    protected function getRedirectUrl(): string
    {
        return PartnerOrganisationResource::getUrl('edit', ['record' => $this->record]);
    }
}
