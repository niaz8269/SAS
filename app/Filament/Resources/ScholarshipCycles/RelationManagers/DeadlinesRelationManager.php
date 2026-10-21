<?php

namespace App\Filament\Resources\ScholarshipCycles\RelationManagers;

use App\Domain\Scholarship\ChangeDeadline;
use App\Enums\DeadlineType;
use App\Models\CycleDeadline;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/**
 * Deadlines are append-only versions (DEAD-03). There is no "edit": changing a date supersedes the old record, audits who
 * changed it, cancels stale reminders and schedules new ones.
 */
class DeadlinesRelationManager extends CycleRelationManager
{
    protected static string $relationship = 'deadlines';

    protected static ?string $title = 'Deadlines (version history)';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('version')->label('v')->sortable(),
            TextColumn::make('route.name')->label('Route')->placeholder('Whole call'),
            TextColumn::make('date_type')->badge()->formatStateUsing(fn ($state) => $state instanceof DeadlineType ? $state->label() : $state),
            TextColumn::make('display')->label('Deadline')->state(function (CycleDeadline $r) {
                $d = app(\App\Domain\Scholarship\DeadlineService::class)->describe($r);

                return $d['primary'].($d['note'] ? ' ('.$d['note'].')' : '');
            })->wrap(),
            TextColumn::make('source_text')->label('As published')->limit(40),
            TextColumn::make('superseded_at')->label('Status')->state(fn (CycleDeadline $r) => $r->superseded_at ? 'Superseded '.$r->superseded_at->format('j M Y') : 'Current')->badge()
                ->color(fn (string $state) => $state === 'Current' ? 'success' : 'gray'),
            TextColumn::make('change_reason')->label('Reason')->limit(40),
        ])->defaultSort('id', 'desc')->headerActions([
            Action::make('change')->label('Set or change deadline')->icon('heroicon-o-calendar-days')->visible(fn () => ! $this->isReadOnly())
                ->schema([
                    Select::make('route_id')->label('Applies to')->options(fn () => $this->getOwnerRecord()->routes()->pluck('name', 'id')->prepend('Whole call', ''))->default(''),
                    Select::make('date_type')->options(DeadlineType::options())->required()->live(),
                    TextInput::make('local_closes_at')->label('Closing date and time (in the source time zone)')->type('datetime-local')
                        ->visible(fn (Get $get) => $get('date_type') === 'exact')->required(fn (Get $get) => $get('date_type') === 'exact'),
                    Select::make('source_timezone')->label('Source time zone (IANA)')->options(array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()))->searchable()
                        ->visible(fn (Get $get) => in_array($get('date_type'), ['exact', 'date_only'], true))->required(fn (Get $get) => $get('date_type') === 'exact'),
                    DatePicker::make('closes_on')->label('Published closing date (no time)')
                        ->visible(fn (Get $get) => $get('date_type') === 'date_only')->required(fn (Get $get) => $get('date_type') === 'date_only')
                        ->helperText('No closing time will be assumed or displayed.'),
                    TextInput::make('source_text')->label('Exactly as published on the source')->maxLength(500),
                    Textarea::make('reason')->label('Reason for the change')->required()->rows(2),
                ])
                ->action(function (array $data) {
                    try {
                        app(ChangeDeadline::class)($this->getOwnerRecord(), $data, auth()->user(), $data['reason'], filled($data['route_id']) ? (int) $data['route_id'] : null);
                        Notification::make()->title('Deadline recorded. Reminders were rescheduled.')->success()->send();
                    } catch (ValidationException $e) {
                        Notification::make()->title('Check the deadline')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();
                    }
                }),
        ]);
    }
}
