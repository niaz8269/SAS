<?php

namespace App\Filament\Resources\Students;

use App\Domain\Admin\Desk;
use App\Domain\Applications\ApplicationFormService;
use App\Domain\Counselling\Counselling;
use App\Domain\Counselling\StudentGaps;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\ApplicationsRelationManager;
use App\Filament\Resources\Students\RelationManagers\CasesRelationManager;
use App\Filament\Resources\Students\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Students\RelationManagers\FollowUpsRelationManager;
use App\Filament\Resources\Students\RelationManagers\MessagesRelationManager;
use App\Models\CounsellorMessage;
use App\Models\Student;
use App\Models\StudentFollowUp;
use App\Models\StudentGoal;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The students a counsellor looks after: their details, application form, documents and applications, the conversation with them,
 * and the counsellor's own notes on where the student stands and what they want. Access follows StudentPolicy.
 */
class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'student';

    protected static ?string $navigationLabel = 'Students';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['latestFollowUp', 'counsellor', 'managedBy', 'goals']);
        $user = auth()->user();
        if ($user?->hasPermission('students.manage')) {
            return $q;
        }

        // A counsellor sees the students assigned to them and the students on their cases.
        return $q->counselledBy($user);
    }

    /** Each student's newest follow-up row, as a query to build filters on. */
    private static function latestFollowUps(): Builder
    {
        return StudentFollowUp::query()->whereIn('id', StudentFollowUp::query()->selectRaw('max(id)')->groupBy('student_id'));
    }

    public static function counsellorOptions(): array
    {
        return User::counsellors()->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('latestFollowUp.status')->label('Status')->badge()->placeholder('Not updated yet')
                    ->formatStateUsing(fn ($state) => StudentFollowUp::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'offer', 'closed' => 'success', 'not_interested', 'on_hold' => 'gray', 'new' => 'warning', default => 'primary'
                    }),
                TextColumn::make('latestFollowUp.wants')->label('What the student wants')->wrap()->limit(70)->placeholder('-'),
                TextColumn::make('latestFollowUp.comment')->label('Last comment')->wrap()->limit(70)->placeholder('-'),
                TextColumn::make('latestFollowUp.created_at')->label('Updated')->since()->placeholder('Never'),
                IconColumn::make('awaiting_reply')->label('Reply needed')->boolean()->trueIcon('heroicon-o-chat-bubble-left-ellipsis')->falseIcon('')->trueColor('warning')
                    ->getStateUsing(fn (Student $r) => Counselling::awaitingReply($r)),
                TextColumn::make('latestFollowUp.follow_up_on')->label('Follow up')->date('j M')->placeholder('-')->color(fn ($state) => $state && $state->lte(today()) ? 'danger' : null),
                TextColumn::make('goals.countries')->label('Countries')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('goals.degree')->label('Degree')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('counsellor.name')->label('Counsellor')->placeholder('Unassigned'),
                TextColumn::make('managedBy.name')->label('Agent')->placeholder('Direct student')->toggleable(),
                TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Registered')->date('j M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(StudentFollowUp::STATUSES)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->whereIn('users.id', static::latestFollowUps()->where('status', $data['value'])->select('student_id')) : $query),
                Filter::make('goal')->label('Goal')->schema([TextInput::make('country')->label('Country'), TextInput::make('degree')->label('Degree')])
                    ->query(function (Builder $query, array $data) {
                        $goals = StudentGoal::query()->select('student_id')
                            ->when(filled($data['country'] ?? null), fn ($g) => $g->where('countries', 'like', '%'.$data['country'].'%'))
                            ->when(filled($data['degree'] ?? null), fn ($g) => $g->where('degree', 'like', '%'.$data['degree'].'%'));

                        return filled($data['country'] ?? null) || filled($data['degree'] ?? null) ? $query->whereIn('users.id', $goals) : $query;
                    }),
                Filter::make('follow_up_due')->label('Follow-up due')->query(fn (Builder $query) => $query->whereIn('users.id', static::latestFollowUps()->whereDate('follow_up_on', '<=', today())->select('student_id'))),
                Filter::make('reply_needed')->label('Reply needed')->query(fn (Builder $query) => $query->whereIn('users.id', CounsellorMessage::query()->select('student_id')->whereIn('id', CounsellorMessage::query()->selectRaw('max(id)')->groupBy('student_id'))->whereColumn('author_id', 'student_id'))),
                Filter::make('no_follow_up')->label('Not updated yet')->query(fn (Builder $query) => $query->whereDoesntHave('followUps')),
                Filter::make('unassigned')->label('No counsellor')->query(fn (Builder $query) => $query->whereNull('users.counsellor_id'))
                    ->visible(fn () => (bool) auth()->user()?->hasPermission('students.manage')),
            ])
            ->recordActions([ViewAction::make()->label('Open')])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkStatus')->label('Update status')->icon('heroicon-o-pencil-square')
                        ->modalHeading('Set the same status on the selected students')->modalDescription('One new note is added to each student you may update. "What the student wants" is kept as it was.')
                        ->schema([
                            Select::make('status')->options(StudentFollowUp::STATUSES)->required(),
                            Textarea::make('comment')->rows(3)->maxLength(2000),
                            DatePicker::make('follow_up_on')->label('Look at them again on')->native(false)->minDate(today()),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $counselling = app(Counselling::class);
                            $done = 0;
                            foreach ($records as $student) {
                                if (auth()->user()->can('update', $student)) {
                                    $counselling->followUp($student, auth()->user(), $data['status'], $student->latestFollowUp?->wants, $data['comment'] ?? null, $data['follow_up_on'] ?? null);
                                    $done++;
                                }
                            }
                            Notification::make()->title($done.' '.str('student')->plural($done).' updated')->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    BulkAction::make('bulkAssignLeast')->label('Assign to the least busy')->icon('heroicon-o-scale')->requiresConfirmation()
                        ->visible(fn () => (bool) auth()->user()?->can('assign', Student::class))
                        ->action(function (Collection $records) {
                            abort_unless(auth()->user()->can('assign', Student::class), 403);
                            foreach ($records as $student) {
                                app(Counselling::class)->assign($student, app(Desk::class)->leastBusy(), auth()->user());
                            }
                            Notification::make()->title($records->count().' '.str('student')->plural($records->count()).' assigned')->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    BulkAction::make('bulkAssign')->label('Assign counsellor')->icon('heroicon-o-user-plus')
                        ->visible(fn () => (bool) auth()->user()?->can('assign', Student::class))
                        ->schema([Select::make('counsellor_id')->label('Counsellor')->options(fn () => static::counsellorOptions())->searchable()->placeholder('Unassigned')])
                        ->action(function (Collection $records, array $data) {
                            abort_unless(auth()->user()->can('assign', Student::class), 403);
                            $counsellor = ($data['counsellor_id'] ?? null) ? User::find($data['counsellor_id']) : null;
                            try {
                                foreach ($records as $student) {
                                    app(Counselling::class)->assign($student, $counsellor, auth()->user());
                                }
                                Notification::make()->title($records->count().' '.str('student')->plural($records->count()).' assigned')->success()->send();
                            } catch (InvalidArgumentException $e) {
                                Notification::make()->title($e->getMessage())->danger()->send();
                            }
                        })->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        $answer = fn (string $key) => fn (Student $record) => $record->applicationForm?->value($key);

        return $schema->components([
            Section::make('Where the student stands')->columns(3)->schema([
                TextEntry::make('latestFollowUp.status')->label('Status')->badge()->placeholder('Not updated yet')->formatStateUsing(fn ($state) => StudentFollowUp::STATUSES[$state] ?? $state),
                TextEntry::make('latestFollowUp.created_at')->label('Updated')->since()->placeholder('Never'),
                TextEntry::make('counsellor.name')->label('Counsellor')->placeholder('Unassigned'),
                TextEntry::make('latestFollowUp.wants')->label('What the student wants')->placeholder('Not written down yet')->columnSpan(3)->prose(),
                TextEntry::make('latestFollowUp.comment')->label('Last comment')->placeholder('No comment yet')->columnSpan(3)->prose(),
            ]),
            Section::make('Aiming for')->columns(3)->collapsible()->schema([
                TextEntry::make('goals.countries')->label('Countries')->placeholder('-'),
                TextEntry::make('goals.degree')->label('Degree')->placeholder('-'),
                TextEntry::make('goals.subject')->label('Subject')->placeholder('-'),
                TextEntry::make('goals.budget')->label('Budget')->placeholder('-'),
                TextEntry::make('goals.intake')->label('Intake')->placeholder('-'),
            ]),
            Section::make('Still missing')->icon('heroicon-o-exclamation-triangle')->collapsible()
                ->visible(fn (Student $record) => app(StudentGaps::class)->for($record)['count'] > 0)
                ->schema([
                    TextEntry::make('missing')->hiddenLabel()->bulleted()->state(function (Student $record) {
                        $gaps = app(StudentGaps::class)->for($record);

                        return collect($gaps['form'] ? ['Application form: '.implode(', ', $gaps['form'])] : [])
                            ->merge(collect($gaps['applications'])->map(fn ($a) => $a['plan']->cycle->programme->title.': '.implode(', ', $a['missing'])))->all();
                    }),
                ]),
            Section::make('Contact')->columns(3)->schema([
                TextEntry::make('name'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('phone')->placeholder('-')->copyable(),
                TextEntry::make('wechat')->label('WeChat')->state($answer('wechat'))->placeholder('-'),
                TextEntry::make('timezone')->label('Time zone'),
                TextEntry::make('managedBy.name')->label('Agent')->placeholder('Direct student'),
            ]),
            Section::make('Overview')->columns(3)->collapsible()->schema([
                TextEntry::make('nationality')->state($answer('nationality'))->placeholder('-'),
                TextEntry::make('date_of_birth')->label('Date of birth')->state($answer('date_of_birth'))->placeholder('-'),
                TextEntry::make('passport_no')->label('Passport number')->state($answer('passport_no'))->placeholder('-'),
                TextEntry::make('highest_education')->label('Highest education')->state(fn (Student $r) => collect([$r->applicationForm?->value('edu_level'), $r->applicationForm?->value('edu_institute')])->filter()->implode(', ') ?: null)->placeholder('-'),
                TextEntry::make('apply_as')->label('Applying for')->state(fn (Student $r) => collect([$r->applicationForm?->value('apply_as'), $r->applicationForm?->value('major')])->filter()->implode(': ') ?: null)->placeholder('-'),
                TextEntry::make('form_state')->label('Application form')->state(function (Student $r) {
                    $form = $r->applicationForm;
                    if (! $form) {
                        return 'Not started';
                    }
                    $percent = app(ApplicationFormService::class)->percent($form);

                    return $percent.'% complete';
                }),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [MessagesRelationManager::class, FollowUpsRelationManager::class, DocumentsRelationManager::class, ApplicationsRelationManager::class, CasesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListStudents::route('/'), 'view' => ViewStudent::route('/{record}')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
