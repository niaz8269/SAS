<?php

namespace App\Filament\Resources\ServiceCases;

use App\Filament\Resources\ServiceCases\Pages\CreateServiceCase;
use App\Filament\Resources\ServiceCases\Pages\EditServiceCase;
use App\Filament\Resources\ServiceCases\Pages\ListServiceCases;
use App\Filament\Resources\ServiceCases\RelationManagers\InvoicesRelationManager;
use App\Models\ScholarshipCycle;
use App\Models\ServiceCase;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** One scholarship application SAS supports for a student: status, counsellor and fees. Access follows ServiceCasePolicy. */
class ServiceCaseResource extends Resource
{
    protected static ?string $model = ServiceCase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'case';

    protected static ?string $navigationLabel = 'Student cases';

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['student', 'advisor', 'invoices']);
        $user = auth()->user();

        return $user?->hasPermission('cases.manage') ? $q : $q->where('advisor_id', $user?->id); // advisors only see their own cases (AUTH-04)
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Case')->columns(2)->schema([
                Select::make('user_id')->label('Student')->required()->searchable()->disabled(fn (?ServiceCase $record) => $record !== null)->dehydrated(fn (?ServiceCase $record) => $record === null)
                    ->options(fn () => User::whereDoesntHave('userRoles')->orderBy('name')->get()->mapWithKeys(fn (User $u) => [$u->id => $u->name.' ('.$u->email.')'])->all())
                    ->helperText('Students with a workspace account. A new case issues the application fee automatically.'),
                Select::make('cycle_id')->label('Scholarship call')->searchable()->placeholder('Not linked to a listing')->disabled(fn (?ServiceCase $record) => $record !== null)->dehydrated(fn (?ServiceCase $record) => $record === null)
                    ->options(fn () => ScholarshipCycle::with('programme')->orderByDesc('id')->limit(300)->get()->mapWithKeys(fn (ScholarshipCycle $c) => [$c->id => $c->programme->title.' ('.$c->intake_year.')'])->all()),
                TextInput::make('title')->maxLength(200)->helperText('Leave empty to use the scholarship name.')->columnSpanFull(),
                Select::make('advisor_id')->label('Counsellor')->searchable()->placeholder('Unassigned')
                    ->options(fn () => User::whereHas('userRoles', fn ($q) => $q->whereIn('role', ['advisor', 'specialist', 'service_manager']))->where('status', 'active')->pluck('name', 'id')->all())
                    ->disabled(fn () => ! auth()->user()->can('cases.manage')),
                Textarea::make('internal_note')->label('Internal note (staff only)')->rows(4)->maxLength(4000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->wrap()->limit(60),
            TextColumn::make('student.name')->label('Student')->searchable(),
            TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => ServiceCase::STATUSES[$state] ?? $state)
                ->color(fn ($state) => match ($state) {
                    'accepted' => 'success', 'rejected', 'withdrawn' => 'danger', 'new' => 'gray', default => 'primary'
                }),
            TextColumn::make('advisor.name')->label('Counsellor')->placeholder('Unassigned'),
            TextColumn::make('due')->label('Awaiting payment')->state(fn (ServiceCase $r) => $r->invoices->where('status', 'issued')->sum('amount_cents') / 100)->money('USD'),
            TextColumn::make('opened_at')->date('j M Y')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(ServiceCase::STATUSES),
            SelectFilter::make('advisor_id')->label('Counsellor')->options(fn () => User::whereHas('userRoles', fn ($q) => $q->whereIn('role', ['advisor', 'specialist', 'service_manager']))->pluck('name', 'id')->all()),
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [InvoicesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListServiceCases::route('/'), 'create' => CreateServiceCase::route('/create'), 'edit' => EditServiceCase::route('/{record}/edit')];
    }
}
