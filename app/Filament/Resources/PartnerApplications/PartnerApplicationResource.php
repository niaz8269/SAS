<?php

namespace App\Filament\Resources\PartnerApplications;

use App\Domain\Agents\PartnerApplications;
use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\PartnerApplications\Pages\ManagePartnerApplications;
use App\Models\PartnerApplication;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Agencies that asked to become partners on the website. Approving one is what creates the partner (agent) account. */
class PartnerApplicationResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = PartnerApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruiting agents';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'partner application';

    protected static ?string $navigationLabel = 'Partner applications';

    protected static function permission(): string
    {
        return 'partners.manage';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::allowed()) {
            return null;
        }
        $n = PartnerApplication::pending()->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        $text = fn (string $name, string $label) => TextInput::make($name)->label($label)->disabled()->dehydrated(false);

        return $schema->components([
            $text('agency_name', 'Agency'), $text('agency_country', 'Country'), $text('city', 'City'), $text('website', 'Website'),
            $text('agency_email', 'Agency email'), $text('agency_phone', 'Agency phone'), $text('registration_number', 'Registration number'),
            TextInput::make('years_in_business')->label('Years in business')->disabled()->dehydrated(false)->formatStateUsing(fn ($state) => PartnerApplication::YEARS[$state] ?? $state),
            TextInput::make('students_per_year')->label('Students per year')->disabled()->dehydrated(false)->formatStateUsing(fn ($state) => PartnerApplication::STUDENTS[$state] ?? $state),
            $text('markets', 'Student countries'),
            Textarea::make('about')->label('About the agency')->disabled()->dehydrated(false)->rows(4)->columnSpanFull(),
            $text('applicant_name', 'Applicant'), $text('applicant_position', 'Position'), $text('applicant_email', 'Applicant email (will be the sign-in)'),
            $text('applicant_phone', 'Applicant phone'), $text('how_heard', 'How they heard about us'), $text('status', 'Status'),
            Textarea::make('review_note')->label('Staff note')->disabled()->dehydrated(false)->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('submitted_at')->label('Sent')->since()->sortable(),
            TextColumn::make('agency_name')->label('Agency')->searchable()->wrap(),
            TextColumn::make('agency_country')->label('Country')->searchable(),
            TextColumn::make('applicant_name')->label('Applicant')->searchable(),
            TextColumn::make('applicant_email')->label('Email')->searchable(),
            TextColumn::make('status')->badge()->color(fn ($s) => match ($s) {
                'pending' => 'warning', 'approved' => 'success', default => 'gray'
            }),
        ])->filters([SelectFilter::make('status')->options(['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected'])->default('pending')])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')->label('Approve')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (PartnerApplication $record) => $record->status === 'pending' && static::allowed())
                    ->requiresConfirmation()
                    ->modalHeading('Approve this partner?')
                    ->modalDescription(fn (PartnerApplication $record) => 'This creates the partner account for '.$record->agency_name.' and emails '.$record->applicant_email.' a link to choose a password.')
                    ->action(function (PartnerApplication $record) {
                        abort_unless(static::allowed(), 403);
                        try {
                            $result = app(PartnerApplications::class)->approve($record, auth()->user());
                        } catch (DomainException $e) {
                            Notification::make()->title('Not approved')->body($e->getMessage())->danger()->send();

                            return;
                        }
                        if ($result['mailed']) {
                            Notification::make()->title('Approved. The partner account was created and the person was emailed.')->success()->send();
                        } else {
                            Notification::make()->title('Approved, but the email could not be sent')->body('The account exists. Open the agency under Recruiting agents and use "Send password reset link" on the person when email works.')->warning()->persistent()->send();
                        }
                    }),
                Action::make('reject')->label('Reject')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (PartnerApplication $record) => $record->status === 'pending' && static::allowed())
                    ->schema([Textarea::make('note')->label('Note to the applicant (optional)')->rows(3)->maxLength(1000)])
                    ->modalHeading('Reject this application?')
                    ->action(function (PartnerApplication $record, array $data) {
                        abort_unless(static::allowed(), 403);
                        try {
                            app(PartnerApplications::class)->reject($record, auth()->user(), $data['note'] ?? null);
                        } catch (DomainException $e) {
                            Notification::make()->title('Not rejected')->body($e->getMessage())->danger()->send();

                            return;
                        }
                        Notification::make()->title('Application rejected')->success()->send();
                    }),
            ])->defaultSort('submitted_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePartnerApplications::route('/')];
    }
}
