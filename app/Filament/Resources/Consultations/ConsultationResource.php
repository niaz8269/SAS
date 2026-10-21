<?php

namespace App\Filament\Resources\Consultations;

use App\Domain\Editorial\AuditLog;
use App\Filament\Resources\Consultations\Pages\ManageConsultations;
use App\Models\Consultation;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** BOOK-01: staff turn a request into a confirmed appointment by choosing a specific time. */
class ConsultationResource extends Resource
{
    protected static ?string $model = Consultation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can('enquiries.view_all') || $u?->can('consultations.assigned'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && (auth()->user()->can('enquiries.view_all') || $record->advisor_id === auth()->id());
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['service', 'advisor']);

        return auth()->user()?->hasPermission('enquiries.view_all') ? $q : $q->where('advisor_id', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->searchable(),
            TextColumn::make('name')->searchable(),
            TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                'confirmed' => 'success', 'requested' => 'warning', default => 'gray'
            }),
            TextColumn::make('preferred_from')->label('Preferred from')->date(),
            TextColumn::make('preferred_to')->label('to')->date()->placeholder('-'),
            TextColumn::make('student_timezone')->label('Student zone'),
            TextColumn::make('confirmed_start_utc')->label('Confirmed (UTC)')->dateTime('j M Y H:i')->placeholder('Not confirmed'),
            TextColumn::make('advisor.name')->label('Advisor')->placeholder('Unassigned'),
        ])->filters([SelectFilter::make('status')->options(['requested' => 'Requested', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'completed' => 'Completed'])])
            ->recordActions([
                Action::make('confirm')->label('Confirm time')->icon('heroicon-o-check-circle')
                    ->visible(fn (Consultation $r) => $r->status === 'requested' && static::canEdit($r))
                    ->schema([
                        TextInput::make('start')->label('Start (local time in the zone below)')->type('datetime-local')->required(),
                        Select::make('timezone')->options(array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()))->searchable()->required()
                            ->default(fn (Consultation $r) => $r->student_timezone),
                        TextInput::make('duration')->label('Minutes')->numeric()->default(30)->required()->minValue(10)->maxValue(240),
                        Select::make('advisor_id')->label('Advisor')->options(fn () => User::whereHas('userRoles', fn ($q) => $q->whereIn('role', ['advisor', 'service_manager']))->where('status', 'active')->pluck('name', 'id'))
                            ->required()->default(fn () => auth()->id()),
                    ])
                    ->action(function (Consultation $record, array $data) {
                        $record->forceFill([
                            'confirmed_start_utc' => CarbonImmutable::parse($data['start'], $data['timezone'])->utc(), 'duration_minutes' => (int) $data['duration'],
                            'advisor_id' => $data['advisor_id'], 'status' => 'confirmed',
                        ])->save();
                        AuditLog::record('consultation.confirmed', $record);
                        Notification::make()->title('Consultation confirmed')->success()->send();
                    }),
                Action::make('complete')->label('Mark completed')->visible(fn (Consultation $r) => $r->status === 'confirmed' && static::canEdit($r))
                    ->requiresConfirmation()->action(fn (Consultation $r) => $r->forceFill(['status' => 'completed'])->save()),
                Action::make('cancel')->label('Cancel')->color('danger')->visible(fn (Consultation $r) => in_array($r->status, ['requested', 'confirmed'], true) && static::canEdit($r))
                    ->requiresConfirmation()->action(fn (Consultation $r) => $r->forceFill(['status' => 'cancelled'])->save()),
            ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageConsultations::route('/')];
    }
}
