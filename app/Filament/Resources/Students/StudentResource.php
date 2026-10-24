<?php

namespace App\Filament\Resources\Students;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Counselling\Counselling;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\ApplicationsRelationManager;
use App\Filament\Resources\Students\RelationManagers\CasesRelationManager;
use App\Filament\Resources\Students\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Students\RelationManagers\FollowUpsRelationManager;
use App\Filament\Resources\Students\RelationManagers\MessagesRelationManager;
use App\Models\ServiceCase;
use App\Models\Student;
use App\Models\StudentFollowUp;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
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
        $q = parent::getEloquentQuery()->with(['latestFollowUp', 'counsellor', 'managedBy']);
        $user = auth()->user();
        if ($user?->hasPermission('students.manage')) {
            return $q;
        }

        // A counsellor sees the students assigned to them and the students on their cases.
        return $q->where(fn (Builder $w) => $w->where('users.counsellor_id', $user?->id)->orWhereIn('users.id', ServiceCase::where('advisor_id', $user?->id)->select('user_id')));
    }

    public static function counsellorOptions(): array
    {
        return User::whereHas('userRoles', fn ($q) => $q->whereIn('role', ['advisor', 'specialist', 'service_manager']))->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();
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
                TextColumn::make('counsellor.name')->label('Counsellor')->placeholder('Unassigned'),
                TextColumn::make('managedBy.name')->label('Agent')->placeholder('Direct student')->toggleable(),
                TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Registered')->date('j M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(StudentFollowUp::STATUSES)
                    ->query(fn (Builder $q, array $data) => filled($data['value'] ?? null) ? $q->whereHas('latestFollowUp', fn ($f) => $f->where('status', $data['value'])) : $q),
                Filter::make('no_follow_up')->label('Not updated yet')->query(fn (Builder $q) => $q->whereDoesntHave('followUps')),
                Filter::make('unassigned')->label('No counsellor')->query(fn (Builder $q) => $q->whereNull('users.counsellor_id'))
                    ->visible(fn () => (bool) auth()->user()?->hasPermission('students.manage')),
            ])
            ->recordActions([ViewAction::make()->label('Open')])
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
