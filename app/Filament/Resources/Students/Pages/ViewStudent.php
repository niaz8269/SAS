<?php

namespace App\Filament\Resources\Students\Pages;

use App\Domain\Counselling\Counselling;
use App\Domain\Editorial\AuditLog;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Audit;
use App\Models\Student;
use App\Models\StudentFollowUp;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
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
        return [
            Action::make('updateStatus')->label('Update status')->icon('heroicon-o-pencil-square')->color('primary')
                ->visible(fn (Student $record) => auth()->user()->can('update', $record))
                ->modalHeading('Where does this student stand?')->modalDescription('Only staff see this. The newest entry is shown in the student list.')
                ->fillForm(fn (Student $record) => ['status' => $record->latestFollowUp?->status ?? 'new', 'wants' => $record->latestFollowUp?->wants])
                ->schema([
                    Select::make('status')->label('Status')->options(StudentFollowUp::STATUSES)->required(),
                    Textarea::make('wants')->label('What the student wants')->rows(3)->maxLength(2000)->helperText('For example the country, degree, subject and scholarship they are aiming for.'),
                    Textarea::make('comment')->label('Comment')->rows(3)->maxLength(2000)->helperText('What happened or what comes next.'),
                ])
                ->action(function (Student $record, array $data) {
                    try {
                        app(Counselling::class)->followUp($record, auth()->user(), $data['status'], $data['wants'] ?? null, $data['comment'] ?? null);
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
    }
}
