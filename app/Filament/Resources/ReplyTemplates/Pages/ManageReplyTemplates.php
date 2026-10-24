<?php

namespace App\Filament\Resources\ReplyTemplates\Pages;

use App\Filament\Resources\ReplyTemplates\ReplyTemplateResource;
use App\Models\ReplyTemplate;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReplyTemplates extends ManageRecords
{
    protected static string $resource = ReplyTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New template')->using(function (array $data, string $model) {
                $user = auth()->user();
                $shared = (bool) ($data['shared'] ?? false) && $user->hasPermission('students.manage');
                $template = new ReplyTemplate(['title' => $data['title'], 'body' => $data['body']]);
                $template->forceFill(['owner_id' => $shared ? null : $user->id])->save();

                return $template;
            }),
        ];
    }
}
