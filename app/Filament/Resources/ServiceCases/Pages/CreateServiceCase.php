<?php

namespace App\Filament\Resources\ServiceCases\Pages;

use App\Domain\Cases\CaseService;
use App\Filament\Resources\ServiceCases\ServiceCaseResource;
use App\Models\ScholarshipCycle;
use App\Models\ServiceCase;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateServiceCase extends CreateRecord
{
    protected static string $resource = ServiceCaseResource::class;

    /** Opening goes through CaseService so the application fee is issued and the event is audited. */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor->can('create', ServiceCase::class), 403);

        return app(CaseService::class)->open(
            User::findOrFail($data['user_id']),
            ! empty($data['cycle_id']) ? ScholarshipCycle::find($data['cycle_id']) : null,
            ! empty($data['advisor_id']) ? User::find($data['advisor_id']) : null,
            ['title' => $data['title'] ?? '', 'internal_note' => $data['internal_note'] ?? null],
            $actor,
        );
    }
}
