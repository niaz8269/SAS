<?php

namespace App\Filament\Resources\Testimonials\Pages;

use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\Testimonial;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTestimonials extends ManageRecords
{
    protected static string $resource = TestimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add a testimonial')->mutateDataUsing(fn (array $data) => $data + ['sort' => ((int) Testimonial::max('sort')) + 10])];
    }
}
