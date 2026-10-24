<?php

namespace App\Filament\Resources\Students\Pages;

use App\Domain\Counselling\Counselling;
use App\Domain\Counselling\ScholarshipMatcher;
use App\Domain\Counselling\StudentGaps;
use App\Domain\Counselling\Timeline;
use App\Domain\Editorial\AuditLog;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Audit;
use App\Models\ScholarshipCycle;
use App\Models\Student;
use App\Models\StudentFollowUp;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Text as Placeholder;
use InvalidArgumentException;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->logOnce('student.viewed'); // who looked at a student's file, once per 15 minutes
    }

    private function logOnce(string $action): void
    {
        $student = $this->getRecord();
        $recent = Audit::where('action', $action)->where('auditable_id', $student->id)->where('auditable_type', $student->getMorphClass())
            ->where('actor_id', auth()->id())->where('created_at', '>', now()->subMinutes(15))->exists();
        $recent || AuditLog::record($action, $student, null, null, null, auth()->user());
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('updateStatus')->label('Update status')->icon('heroicon-o-pencil-square')->color('primary')
                ->visible(fn (Student $record) => auth()->user()->can('update', $record))
                ->modalHeading('Where does this student stand?')->modalDescription('Only staff see this. The newest entry is shown in the student list.')
                ->fillForm(fn (Student $record) => ['status' => $record->latestFollowUp?->status ?? 'new', 'wants' => $record->latestFollowUp?->wants])
                ->schema([
                    Select::make('status')->label('Status')->options(StudentFollowUp::STATUSES)->required(),
                    Textarea::make('wants')->label('What the student wants')->rows(3)->maxLength(2000)->helperText('For example the country, degree, subject and scholarship they are aiming for.'),
                    Textarea::make('comment')->label('Comment')->rows(3)->maxLength(2000)->helperText('What happened or what comes next.'),
                    DatePicker::make('follow_up_on')->label('Look at this student again on')->native(false)->minDate(today())->helperText('Optional. The student shows under Today on that day and you get a reminder email.'),
                ])
                ->action(function (Student $record, array $data) {
                    try {
                        app(Counselling::class)->followUp($record, auth()->user(), $data['status'], $data['wants'] ?? null, $data['comment'] ?? null, $data['follow_up_on'] ?? null);
                        Notification::make()->title('Saved')->success()->send();
                        $this->getRecord()->unsetRelation('latestFollowUp');
                        $this->refreshFormData(['latestFollowUp']);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('applicationForm')->label('Application form')->icon('heroicon-o-clipboard-document-list')
                ->visible(fn (Student $record) => auth()->user()->can('view', $record) && auth()->user()->counsels($record))
                ->modalHeading('Application form')->modalSubmitAction(false)->modalCancelActionLabel('Close')->modalWidth('4xl')
                ->modalContent(function (Student $record) {
                    $this->logOnce('student.form_viewed');

                    return view('filament.student-application-form', ['form' => $record->applicationForm]);
                }),
            Action::make('contact')->label('Contact')->icon('heroicon-o-envelope')->color('gray')
                ->visible(fn (Student $record) => auth()->user()->can('view', $record))
                ->modalHeading('Contact the student')->modalSubmitAction(false)->modalCancelActionLabel('Close')
                ->modalContent(fn (Student $record) => view('filament.student-contact', ['student' => $record, 'wechat' => $record->applicationForm?->value('wechat')])),
            Action::make('timeline')->label('Timeline')->icon('heroicon-o-clock')->color('gray')
                ->visible(fn (Student $record) => auth()->user()->can('view', $record))
                ->modalHeading('Everything that happened')->modalSubmitAction(false)->modalCancelActionLabel('Close')->modalWidth('3xl')
                ->modalContent(fn (Student $record) => view('filament.student-timeline', ['events' => app(Timeline::class)->for($record)])),
            Action::make('goals')->label('Goals')->icon('heroicon-o-flag')->color('gray')
                ->visible(fn (Student $record) => auth()->user()->can('update', $record))
                ->modalHeading('What the student is aiming for')->modalDescription('Used to filter the student list and to suggest scholarships.')
                ->fillForm(fn (Student $record) => $record->goals?->only(['countries', 'degree', 'subject', 'budget', 'intake']) ?? [])
                ->schema([
                    TextInput::make('countries')->label('Countries')->maxLength(300)->helperText('Separate with commas, for example China, Turkey, Hungary.'),
                    TextInput::make('degree')->label('Degree')->maxLength(60)->datalist(['Bachelor', "Master's", 'PhD', 'Diploma', 'Language course']),
                    TextInput::make('subject')->label('Subject or field')->maxLength(200),
                    TextInput::make('budget')->label('Budget')->maxLength(120)->helperText('For example "full scholarship only" or "up to US$3,000 a year".'),
                    TextInput::make('intake')->label('Intake')->maxLength(60)->helperText('For example September 2027.'),
                ])
                ->action(function (Student $record, array $data) {
                    app(Counselling::class)->setGoals($record, auth()->user(), $data);
                    Notification::make()->title('Goals saved')->success()->send();
                    $this->refreshFormData(['goals']);
                }),
            Action::make('matches')->label('Suggest scholarships')->icon('heroicon-o-sparkles')->color('gray')
                ->visible(fn (Student $record) => auth()->user()->can('update', $record))
                ->modalHeading('Scholarships that fit the goals')->modalSubmitActionLabel('Send the shortlist')
                ->schema(function (Student $record) {
                    $matches = app(ScholarshipMatcher::class)->for($record);

                    return [
                        $matches->isEmpty()
                            ? Placeholder::make('Nothing matches yet. Fill in the goals (countries, degree, subject) first, or the student has already applied to everything that fits.')
                            : CheckboxList::make('cycles')->label('Tick the ones to send to the student')->options($matches->mapWithKeys(fn ($m) => [$m['cycle']->id => $m['cycle']->displayName().' ('.implode(', ', $m['why']).')'])->all()),
                    ];
                })
                ->action(function (Student $record, array $data) {
                    $ids = $data['cycles'] ?? [];
                    if ($ids === []) {
                        Notification::make()->title('Nothing was ticked, so nothing was sent')->warning()->send();

                        return;
                    }
                    $names = ScholarshipCycle::with('programme')->whereIn('id', $ids)->get()->map(fn ($c) => '- '.$c->displayName())->implode("\n");
                    app(Counselling::class)->message($record, auth()->user(), "Hello {$record->name}, these scholarships fit what you told me:\n".$names."\nOpen Choose scholarships in your workspace and press Apply on the ones you like. Tell me which you want to go for.");
                    Notification::make()->title('Shortlist sent to the student')->success()->send();
                }),
            Action::make('remind')->label('Ask for what is missing')->icon('heroicon-o-bell-alert')->color('gray')
                ->visible(fn (Student $record) => auth()->user()->can('update', $record) && app(StudentGaps::class)->for($record)['count'] > 0)
                ->requiresConfirmation()->modalHeading('Remind the student of what is missing?')
                ->modalDescription(fn (Student $record) => 'This message is sent to the student: '.str(app(StudentGaps::class)->reminder($record, app(StudentGaps::class)->for($record)))->limit(500))
                ->action(function (Student $record) {
                    $gaps = app(StudentGaps::class);
                    app(Counselling::class)->message($record, auth()->user(), $gaps->reminder($record, $gaps->for($record)));
                    Notification::make()->title('Reminder sent')->success()->send();
                }),
            Action::make('zip')->label('Download all documents')->icon('heroicon-o-archive-box-arrow-down')->color('gray')
                ->visible(fn (Student $record) => (bool) auth()->user()->counsels($record))
                ->url(fn (Student $record) => route('students.documents.zip', $record->public_id), shouldOpenInNewTab: true),
            Action::make('assign')->label('Assign counsellor')->icon('heroicon-o-user-plus')->color('gray')
                ->visible(fn () => auth()->user()->can('assign', Student::class))
                ->fillForm(fn (Student $record) => ['counsellor_id' => $record->counsellor_id])
                ->schema([Select::make('counsellor_id')->label('Counsellor')->options(fn () => StudentResource::counsellorOptions())->searchable()->placeholder('Unassigned')])
                ->action(function (Student $record, array $data) {
                    try {
                        app(Counselling::class)->assign($record, ($data['counsellor_id'] ?? null) ? User::find($data['counsellor_id']) : null, auth()->user());
                        Notification::make()->title('Counsellor saved')->success()->send();
                        $this->refreshFormData(['counsellor_id']);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];

        // The three everyday buttons stay in view; the rest sit under More.
        return [...array_slice($actions, 0, 3), ActionGroup::make(array_slice($actions, 3))->label('More')->icon('heroicon-m-ellipsis-horizontal')->button()->color('gray')];
    }
}
