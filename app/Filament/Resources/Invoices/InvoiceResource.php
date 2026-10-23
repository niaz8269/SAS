<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\ServiceCases\RelationManagers\InvoiceActions;
use App\Filament\Resources\ServiceCases\RelationManagers\InvoiceColumns;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Finance view of every invoice: record payments, cancel unpaid invoices, record refunds. Access follows InvoicePolicy. */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Invoices and payments';

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['serviceCase', 'student']);
        $user = auth()->user();

        return ($user?->hasPermission('finance.view') || $user?->hasPermission('cases.manage')) ? $q : $q->whereHas('serviceCase', fn ($c) => $c->where('advisor_id', $user?->id));
    }

    public static function canCreate(): bool
    {
        return false; // invoices are issued by the case rules
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('student.name')->label('Student')->searchable(),
            TextColumn::make('serviceCase.title')->label('Case')->limit(40)->wrap(),
            ...InvoiceColumns::all(),
            TextColumn::make('issued_at')->date('j M Y')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(Invoice::STATUSES),
            SelectFilter::make('kind')->options(Invoice::KINDS),
            SelectFilter::make('refund_status')->label('Refund')->options(['due' => 'Refund due', 'paid' => 'Refunded']),
        ])->recordActions(InvoiceActions::all())->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListInvoices::route('/')];
    }
}
