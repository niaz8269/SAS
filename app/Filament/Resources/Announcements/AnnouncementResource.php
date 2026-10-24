<?php

namespace App\Filament\Resources\Announcements;

use App\Domain\Admin\Announcements;
use App\Domain\Journey\Journey;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Models\Announcement;
use App\Models\PartnerOrganisation;
use App\Models\ScholarshipProgramme;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** Messages to a group of students, for example when a deadline changes. Save, check who it reaches, then send once. */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'announcement';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isStaff() && auth()->user()->hasPermission('students.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && ! $record->isSent();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && ! $record->isSent();
    }

    private const VALUE_FIELDS = ['programme' => 'value_programme', 'deadline' => 'value_days', 'agent' => 'value_agent', 'counsellor' => 'value_counsellor', 'journey' => 'value_journey'];

    /** The form has one field per kind of audience; the table keeps the chosen one in udience_value. */
    public static function toRecord(array $data): array
    {
        $field = self::VALUE_FIELDS[$data['audience'] ?? ''] ?? null;

        return ['title' => $data['title'], 'body' => $data['body'], 'audience' => $data['audience'], 'audience_value' => $field ? (string) ($data[$field] ?? '') : null];
    }

    public static function toForm(array $data): array
    {
        $field = self::VALUE_FIELDS[$data['audience'] ?? ''] ?? null;
        $field && $data[$field] = $data['audience_value'] ?? null;

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(160)->columnSpanFull(),
            Textarea::make('body')->label('Message')->required()->rows(6)->maxLength(2000)->columnSpanFull(),
            Select::make('audience')->label('Who is it for?')->options(Announcement::AUDIENCES)->required()->default('all')->live(),
            Select::make('value_programme')->label('Which scholarship')->visible(fn ($get) => $get('audience') === 'programme')->required(fn ($get) => $get('audience') === 'programme')->searchable()
                ->options(fn () => ScholarshipProgramme::orderBy('title')->limit(300)->pluck('title', 'id')->all()),
            TextInput::make('value_days')->label('Closing within how many days')->numeric()->minValue(1)->maxValue(365)->default(14)->visible(fn ($get) => $get('audience') === 'deadline')->required(fn ($get) => $get('audience') === 'deadline'),
            Select::make('value_agent')->label('Which agency')->visible(fn ($get) => $get('audience') === 'agent')->required(fn ($get) => $get('audience') === 'agent')
                ->options(fn () => PartnerOrganisation::where('kind', 'agent')->orderBy('name')->pluck('name', 'id')->all()),
            Select::make('value_counsellor')->label('Which counsellor')->visible(fn ($get) => $get('audience') === 'counsellor')->required(fn ($get) => $get('audience') === 'counsellor')
                ->options(fn () => User::counsellors()->orderBy('name')->pluck('name', 'id')->all()),
            Select::make('value_journey')->label('Which stage')->visible(fn ($get) => $get('audience') === 'journey')->required(fn ($get) => $get('audience') === 'journey')
                ->options(fn () => collect(config('journey.staff_stages'))->mapWithKeys(fn ($s) => [$s => app(Journey::class)->label($s)])->all()),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->weight('semibold'),
                TextColumn::make('audience')->label('For')->state(fn (Announcement $r) => ucfirst(app(Announcements::class)->describe($r)))->wrap(),
                TextColumn::make('recipients')->label('Reached')->state(fn (Announcement $r) => $r->isSent() ? $r->recipients : 'Not sent'),
                TextColumn::make('sent_at')->label('Sent')->dateTime('j M Y, H:i')->placeholder('-'),
                TextColumn::make('author.name')->label('By')->placeholder('-'),
            ])
            ->recordActions([
                Action::make('send')->label('Send')->icon('heroicon-o-paper-airplane')->color('success')
                    ->visible(fn (Announcement $r) => static::canEdit($r))
                    ->requiresConfirmation()->modalHeading('Send this announcement?')
                    ->modalDescription(function (Announcement $r) {
                        $who = app(Announcements::class)->recipients($r);

                        return 'It reaches '.$who->count().' '.str('student')->plural($who->count()).' ('.app(Announcements::class)->describe($r).'). It cannot be recalled.'.($who->isEmpty() ? '' : ' For example: '.$who->take(3)->pluck('name')->implode(', ').'.');
                    })
                    ->action(function (Announcement $r) {
                        abort_unless(static::canEdit($r), 403);
                        try {
                            $count = app(Announcements::class)->send($r, auth()->user());
                            Notification::make()->title('Sent to '.$count.' '.str('student')->plural($count))->success()->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make()->visible(fn (Announcement $r) => static::canEdit($r))->mutateRecordDataUsing(fn (array $data) => static::toForm($data))->using(function (Announcement $record, array $data) {
                    $record->update(static::toRecord($data));

                    return $record;
                }),
                DeleteAction::make()->visible(fn (Announcement $r) => static::canDelete($r)),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No announcements yet');
    }

    public static function getPages(): array
    {
        return ['index' => ManageAnnouncements::route('/')];
    }
}
