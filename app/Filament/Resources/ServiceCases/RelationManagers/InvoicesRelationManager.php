<?php

namespace App\Filament\Resources\ServiceCases\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The fees of one case. Counsellors can see them; only finance and managers (invoices.manage) record payments and refunds. */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Fees and payments';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true; // changes happen through the named actions below
    }

    public function table(Table $table): Table
    {
        return $table->columns(InvoiceColumns::all())->recordActions(InvoiceActions::all());
    }
}
