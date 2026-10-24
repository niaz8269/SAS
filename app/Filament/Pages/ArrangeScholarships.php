<?php

namespace App\Filament\Pages;

use App\Models\ScholarshipCycle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class ArrangeScholarships extends ArrangePage
{
    protected static ?string $title = 'Order of scholarships';

    protected static ?string $navigationLabel = 'Order scholarships';

    protected static ?string $slug = 'order-scholarships';

    protected static ?int $navigationSort = 13;

    protected function modelClass(): string
    {
        return ScholarshipCycle::class;
    }

    protected function ordered(): Builder
    {
        // the same order the scholarship directory uses by default, so "start arranging" changes nothing visible
        return ScholarshipCycle::query()->visible()->with('programme')->manualOrder()
            ->orderByRaw('featured DESC')->orderByRaw('next_closing_sort IS NULL')->orderBy('next_closing_sort')->orderByDesc('id');
    }

    protected function nameColumns(): array
    {
        return [
            TextColumn::make('programme.title')->label('Scholarship')->description(fn (ScholarshipCycle $r) => $r->keyLabel()),
            IconColumn::make('featured')->boolean()->label('Featured'),
            TextColumn::make('sort_order')->label('Set position')->placeholder('automatic'),
        ];
    }

    public function intro(): string
    {
        return 'The order of the scholarship list (when it is sorted by "Relevance") and the featured box on the homepage. Drag a row, or use the arrows: to the top, to the bottom, or to any position number. Search words still put matching titles first.';
    }

    protected function noun(): string
    {
        return 'scholarships';
    }
}
