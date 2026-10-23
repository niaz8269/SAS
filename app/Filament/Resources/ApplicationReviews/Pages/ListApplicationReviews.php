<?php

namespace App\Filament\Resources\ApplicationReviews\Pages;

use App\Filament\Resources\ApplicationReviews\ApplicationReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListApplicationReviews extends ListRecords
{
    protected static string $resource = ApplicationReviewResource::class;
}
