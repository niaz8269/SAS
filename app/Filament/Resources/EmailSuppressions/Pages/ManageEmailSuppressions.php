<?php

namespace App\Filament\Resources\EmailSuppressions\Pages;

use App\Filament\Resources\EmailSuppressions\EmailSuppressionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEmailSuppressions extends ManageRecords
{
    protected static string $resource = EmailSuppressionResource::class;
}
