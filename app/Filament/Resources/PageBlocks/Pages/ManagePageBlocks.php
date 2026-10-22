<?php

namespace App\Filament\Resources\PageBlocks\Pages;

use App\Filament\Resources\PageBlocks\PageBlockResource;
use App\Models\PageBlock;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManagePageBlocks extends ManageRecords
{
    protected static string $resource = PageBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add a section')
                ->mutateDataUsing(fn (array $data) => $data + ['key' => 'custom-'.Str::lower(Str::random(10)), 'sort' => ((int) PageBlock::max('sort')) + 10]),
        ];
    }
}
