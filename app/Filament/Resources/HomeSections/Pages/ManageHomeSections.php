<?php

namespace App\Filament\Resources\HomeSections\Pages;

use App\Filament\Resources\HomeSections\HomeSectionResource;
use App\Models\HomeSection;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageHomeSections extends ManageRecords
{
    protected static string $resource = HomeSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add a section')
                ->mutateDataUsing(fn (array $data) => $data + ['key' => 'custom-'.Str::lower(Str::random(10)), 'sort' => ((int) HomeSection::max('sort')) + 10]),
        ];
    }
}
