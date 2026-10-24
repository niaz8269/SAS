<?php

namespace App\Filament\Pages;

use App\Models\University;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class ArrangeUniversities extends ArrangePage
{
    protected static ?string $title = 'Order of universities';

    protected static ?string $navigationLabel = 'Order universities';

    protected static ?string $slug = 'order-universities';

    protected static ?int $navigationSort = 12;

    protected function modelClass(): string
    {
        return University::class;
    }

    protected function ordered(): Builder
    {
        return University::query()->inCatalogue()->manualOrder()->orderBy('name');
    }

    protected function nameColumns(): array
    {
        return [
            TextColumn::make('name')->label('University')->description(fn (University $r) => $r->name_zh),
            TextColumn::make('province')->label('Province or city')->toggleable(),
            TextColumn::make('sort_order')->label('Set position')->placeholder('automatic'),
        ];
    }

    public function intro(): string
    {
        return 'The order of the universities list and the homepage. Drag a row, or use the arrows: to the top, to the bottom, or to any position number. Only universities that are listed on the website are shown here.';
    }

    protected function noun(): string
    {
        return 'universities';
    }
}
