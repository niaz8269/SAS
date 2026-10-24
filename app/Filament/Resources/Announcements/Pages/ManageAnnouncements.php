<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAnnouncements extends ManageRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New announcement')->using(function (array $data) {
                $announcement = new Announcement(AnnouncementResource::toRecord($data));
                $announcement->forceFill(['created_by' => auth()->id()])->save();

                return $announcement;
            }),
        ];
    }
}
