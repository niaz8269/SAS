<?php

namespace App\Filament\Resources\ServiceCases;

use App\Domain\Admin\Desk;
use App\Domain\Cases\CaseService;
use App\Filament\Resources\ServiceCases\Pages\CreateServiceCase;
use App\Filament\Resources\ServiceCases\Pages\EditServiceCase;
use App\Filament\Resources\ServiceCases\Pages\ListServiceCases;
use App\Filament\Resources\ServiceCases\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\ServiceCases\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\ServiceCases\RelationManagers\SharedDocumentsRelationManager;
use App\Models\DegreeLevel;
use App\Models\ScholarshipCycle;
use App\Models\ServiceCase;
use App\Models\Student;
use App\Models\StudentFollowUp;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
                    // Only the case's own student is listed on an existing case; a new case finds a student by typing 2 or more letters (name or email), for staff who may manage cases.
                    ->options(fn (?ServiceCase $record) => $record ? [$record->user_id => (string) $record->student?->name] : [])
                    ->getSearchResultsUsing(fn (string $search) => auth()->user()?->can('cases.manage') && mb_strlen(trim($search)) >= 2
                        ? User::whereDoesntHave('userRoles')->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes(trim($search), '%_\\').'%')->orWhere('email', 'like', '%'.addcslashes(trim($search), '%_\\').'%'))
                            ->orderBy('name')->limit(20)->get()->mapWithKeys(fn (User $u) => [$u->id => $u->name.' ('.$u->email.')'])->all()
                        : [])
                    ->getOptionLabelUsing(fn ($value) => ($u = User::whereDoesntHave('userRoles')->find($value)) ? $u->name.' ('.$u->email.')' : null)
                    ->helperText('Students with a workspace account: type at least 2 letters of the name or email. A new case issues the application fee automatically.'),
                Select::make('cycle_id')->label('Scholarship call')->searchable()->placeholder('Not linked to a listing')->disabled(fn (?ServiceCase $record) => $record !== null)->dehydrated(fn (?ServiceCase $record) => $record === null)
                    ->options(fn () => ScholarshipCycle::with('programme')->orderByDesc('id')->limit(300)->get()->mapWithKeys(fn (ScholarshipCycle $c) => [$c->id => $c->programme->title.' ('.$c->intake_year.')'])->all()),
                TextInput::make('title')->maxLength(200)->helperText('Leave empty to use the scholarship name.')->columnSpanFull(),
                Select::make('degree_level_id')->label('Degree level')->placeholder('Not chosen yet')->visible(fn (?ServiceCase $record) => $record !== null)
                    ->options(fn () => DegreeLevel::orderBy('sort')->pluck('name', 'id')->all())->helperText('Decides which document rules apply. The student can set it too.'),
                Select::make('advisor_id')->label('Counsellor')->searchable()->placeholder('Unassigned')
                    ->options(fn () => User::counsellors()->pluck('name', 'id')->all())
                    ->disabled(fn () => ! auth()->user()->can('cases.manage')),
                Textarea::make('internal_note')->label('Internal note (staff only)')->rows(4)->maxLength(4000)->columnSpanFull(),
            ]),
            Section::make("The student on the counsellor's file")->description('The same notes the counsellor keeps, so you can see the whole picture from the case.')->columns(2)
                ->visible(fn (?ServiceCase $record) => $record !== null && auth()->user()->can('students.manage'))
                ->schema([
                    Text::make(fn (?ServiceCase $record) => static::studentLine($record, 'who'))->columnSpanFull(),
                    Text::make(fn (?ServiceCase $record) => static::studentLine($record, 'wants'))->columnSpanFull(),
                    Text::make(fn (?ServiceCase $record) => static::studentLine($record, 'comment'))->columnSpanFull(),
                    Text::make(fn (?ServiceCase $record) => static::studentLine($record, 'goals'))->columnSpanFull(),
                ]),
        ]);
    }

    /** One line of the student's counsellor file, for the panel on the case page. */
    public static function studentLine(?ServiceCase $case, string $part): string
    {
        $student = $case ? Student::with(['latestFollowUp', 'counsellor', 'goals'])->find($case->user_id) : null;
        if (! $student) {
            return '';
        }
        $f = $student->latestFollowUp;

        return match ($part) {
            'who' => $student->name.' - counsellor: '.($student->counsellor?->name ?? 'none').' - status: '.($f ? StudentFollowUp::STATUSES[$f->status].' ('.$f->created_at->diffForHumans().')' : 'not updated yet').($f?->follow_up_on ? ' - follow up '.$f->follow_up_on->format('j M') : ''),
            'wants' => 'Wants: '.($f?->wants ?: 'not written down yet'),
            'comment' => 'Last comment: '.($f?->comment ?: 'none'),
            'goals' => 'Aiming for: '.($student->goals ? collect([$student->goals->countries, $student->goals->degree, $student->goals->subject, $student->goals->intake])->filter()->implode(', ') : 'not set'),
            default => '',
        };
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
            IconColumn::make('awaiting_reply')->label('Reply needed')->boolean()->trueIcon('heroicon-o-chat-bubble-left-ellipsis')->falseIcon('')->trueColor('warning'),
            TextColumn::make('due')->label('Awaiting payment')->state(fn (ServiceCase $r) => $r->invoices->where('status', 'issued')->sum('amount_cents') / 100)->money('USD'),
            TextColumn::make('opened_at')->date('j M Y')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(ServiceCase::STATUSES),
            SelectFilter::make('advisor_id')->label('Counsellor')->options(fn () => User::counsellors()->pluck('name', 'id')->all()),
            Filter::make('no_counsellor')->label('No counsellor')->query(fn (Builder $query) => $query->whereNull('advisor_id')),
        ])->recordActions([EditAction::make()])->toolbarActions([
            BulkActionGroup::make([
                BulkAction::make('assignLeastBusy')->label('Assign to the least busy')->icon('heroicon-o-scale')->requiresConfirmation()
                    ->visible(fn () => (bool) auth()->user()?->can('cases.manage'))
                    ->action(function (Collection $records) {
                        abort_unless(auth()->user()->can('cases.manage'), 403);
                        foreach ($records as $case) {
                            app(CaseService::class)->assign($case, app(Desk::class)->leastBusy(), auth()->user());
                        }
                        Notification::make()->title($records->count().' '.str('case')->plural($records->count()).' assigned')->success()->send();
                    })->deselectRecordsAfterCompletion(),
                BulkAction::make('assignTo')->label('Assign to...')->icon('heroicon-o-user-plus')
                    ->visible(fn () => (bool) auth()->user()?->can('cases.manage'))
                    ->schema([Select::make('advisor_id')->label('Counsellor')->options(fn () => User::counsellors()->pluck('name', 'id'))->searchable()->required()])
                    ->action(function (Collection $records, array $data) {
                        abort_unless(auth()->user()->can('cases.manage'), 403);
                        foreach ($records as $case) {
                            app(CaseService::class)->assign($case, User::find($data['advisor_id']), auth()->user());
                        }
                        Notification::make()->title($records->count().' '.str('case')->plural($records->count()).' assigned')->success()->send();
                    })->deselectRecordsAfterCompletion(),
            ]),
        ])->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [MessagesRelationManager::class, SharedDocumentsRelationManager::class, InvoicesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListServiceCases::route('/'), 'create' => CreateServiceCase::route('/create'), 'edit' => EditServiceCase::route('/{record}/edit')];
    }
}
