<?php

namespace App\Filament\Resources\AvailabilitySlots;

use App\Filament\Resources\AvailabilitySlots\Pages\ManageAvailabilitySlots;
use App\Models\AvailabilitySlot;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** The times a counsellor is free for a call. Students book them from their workspace; a booking appears in My calendar. */
class AvailabilitySlotResource extends Resource
{
    protected static ?string $model = AvailabilitySlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'free time';

    protected static ?string $navigationLabel = 'My availability';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isStaff() && auth()->user()->hasPermission('students.assigned');
    }

    public static function canCreate(): bool
    {
        return false; // slots are added in bulk with "Add free times"
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->isBooked() && ($record->counsellor_id === auth()->id() || auth()->user()?->hasPermission('students.manage'));
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()->with(['counsellor', 'student'])->where('starts_at', '>', now()->subDay())
            ->when(! $user?->hasPermission('students.manage'), fn (Builder $q) => $q->where('counsellor_id', $user?->id));
    }

    public static function table(Table $table): Table
    {
        $tz = fn () => auth()->user()?->timezone ?: 'UTC';

        return $table
            ->columns([
                TextColumn::make('starts_at')->label('When')->state(fn (AvailabilitySlot $r) => $r->starts_at->copy()->timezone($tz())->format('D j M Y, H:i'))->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('starts_at', $direction)),
                TextColumn::make('duration_minutes')->label('Minutes'),
                TextColumn::make('counsellor.name')->label('Counsellor')->visible(fn () => (bool) auth()->user()?->hasPermission('students.manage')),
                TextColumn::make('student.name')->label('Booked by')->placeholder('Free')->badge()->color(fn ($state) => $state ? 'success' : 'gray'),
            ])
            ->filters([Filter::make('free')->label('Only free times')->query(fn (Builder $query) => $query->whereNull('booked_by'))])
            ->recordActions([DeleteAction::make()->label('Remove')->visible(fn (AvailabilitySlot $r) => static::canDelete($r))])
            ->defaultSort('starts_at')
            ->emptyStateHeading('No free times yet')
            ->emptyStateDescription('Press "Add free times" and students can book a call with you.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageAvailabilitySlots::route('/')];
    }
}
