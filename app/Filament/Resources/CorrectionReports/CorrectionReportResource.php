<?php

namespace App\Filament\Resources\CorrectionReports;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\CorrectionReports\Pages\ManageCorrectionReports;
use App\Models\CorrectionReport;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CorrectionReportResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = CorrectionReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Editorial';

    protected static ?int $navigationSort = 4;

    protected static function permission(): string
    {
        return 'corrections.manage';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('message')->disabled()->dehydrated(false)->rows(5)->columnSpanFull(),
            TextInput::make('reporter_email')->disabled()->dehydrated(false),
            Select::make('status')->options(['new' => 'New', 'reviewing' => 'Reviewing', 'resolved' => 'Resolved', 'rejected' => 'Rejected'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->since()->sortable(),
            TextColumn::make('cycle.programme.title')->label('Listing')->placeholder('General'),
            TextColumn::make('message')->limit(80)->wrap(),
            TextColumn::make('status')->badge()->color(fn ($s) => match ($s) { 'new' => 'danger', 'reviewing' => 'warning', 'resolved' => 'success', default => 'gray' }),
        ])->filters([SelectFilter::make('status')->options(['new' => 'New', 'reviewing' => 'Reviewing', 'resolved' => 'Resolved', 'rejected' => 'Rejected'])->default('new')])
            ->recordActions([EditAction::make()->mutateDataUsing(fn (array $data) => $data + ['handled_by' => auth()->id()])])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageCorrectionReports::route('/')];
    }
}
