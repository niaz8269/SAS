<?php

namespace App\Filament\Resources\ScholarshipCycles\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;

/** Child records follow the permission of their cycle: you may edit them only where you may edit the cycle (AUTH-03). */
abstract class CycleRelationManager extends RelationManager
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('editDirect', $this->getOwnerRecord());
    }
}
