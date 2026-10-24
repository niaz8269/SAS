<?php

namespace App\Filament\Resources\Enquiries;

use App\Domain\Admin\Desk;
use App\Domain\Editorial\AuditLog;
use App\Enums\EnquiryStatus;
use App\Filament\Resources\Enquiries\Pages\EditEnquiry;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Enquiries\RelationManagers\MessagesRelationManager;
use App\Models\Enquiry;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** ORD-01 enquiry lifecycle. Access follows EnquiryPolicy: staff only see what they are assigned unless they hold enquiries.view_all. */
class EnquiryResource extends Resource
{
    protected static ?string $model = Enquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['service', 'owner']);
        $user = auth()->user();

        return $user?->hasPermission('enquiries.view_all') ? $q : $q->where('owner_id', $user?->id); // scope by assignment (AUTH-04)
    }

    public static function canCreate(): bool
    {
        return false; // enquiries come from the public form
    }

    /** Gives an enquiry an owner (a new one becomes "assigned") and records who did it. */
    public static function setOwner(Enquiry $enquiry, ?User $owner): void
    {
        $before = $enquiry->only(['status', 'owner_id']);
        $enquiry->forceFill(['owner_id' => $owner?->id]);
        if ($owner && $enquiry->status === EnquiryStatus::New) {
            $enquiry->status = EnquiryStatus::Assigned;
        }
        $enquiry->save();
        AuditLog::record('enquiry.updated', $enquiry, $before, $enquiry->only(['status', 'owner_id']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Enquiry')->columns(2)->schema([
                TextInput::make('reference')->disabled()->dehydrated(false),
                TextInput::make('category')->disabled()->dehydrated(false),
                TextInput::make('name')->disabled()->dehydrated(false),
                TextInput::make('email')->disabled()->dehydrated(false),
                TextInput::make('phone')->disabled()->dehydrated(false),
                TextInput::make('contact_preference')->disabled()->dehydrated(false),
                Textarea::make('message')->disabled()->dehydrated(false)->rows(6)->columnSpanFull(),
            ]),
            Section::make('Handling')->columns(2)->schema([
                Select::make('status')->options(EnquiryStatus::options())->required(),
                Select::make('owner_id')->label('Assigned to')->options(fn () => User::counsellors()->pluck('name', 'id'))
                    ->searchable()->placeholder('Unassigned')->disabled(fn () => ! auth()->user()->can('enquiries.assign')),
                TextInput::make('next_action')->maxLength(190),
                DateTimePicker::make('next_action_at'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->searchable()->sortable(),
            TextColumn::make('created_at')->since()->sortable(),
            TextColumn::make('category')->badge(),
            TextColumn::make('name')->searchable(),
            TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state instanceof EnquiryStatus ? $state->label() : $state),
            TextColumn::make('owner.name')->label('Owner')->placeholder('Unassigned'),
            TextColumn::make('duplicate_of_id')->label('Repeat?')->formatStateUsing(fn ($state) => $state ? 'Possible repeat' : '')->badge()->color('warning'),
            TextColumn::make('next_action_at')->dateTime('j M H:i')->placeholder('-'),
        ])->filters([
            SelectFilter::make('status')->options(EnquiryStatus::options()),
            Filter::make('unassigned')->query(fn (Builder $query) => $query->whereNull('owner_id'))->label('Unassigned'),
            Filter::make('no_reply')->label('No reply yet')->query(fn (Builder $query) => $query->whereNull('first_response_at')->whereNotIn('status', ['closed', 'converted'])),
        ])->recordActions([EditAction::make()])->toolbarActions([
            BulkActionGroup::make([
                BulkAction::make('assignLeastBusy')->label('Assign to the least busy')->icon('heroicon-o-scale')
                    ->visible(fn () => (bool) auth()->user()?->can('enquiries.assign'))->requiresConfirmation()
                    ->action(function (Collection $records) {
                        abort_unless(auth()->user()->can('enquiries.assign'), 403);
                        foreach ($records as $enquiry) {
                            static::setOwner($enquiry, app(Desk::class)->leastBusy());
                        }
                        Notification::make()->title($records->count().' '.str('enquiry')->plural($records->count()).' assigned')->success()->send();
                    })->deselectRecordsAfterCompletion(),
                BulkAction::make('assignTo')->label('Assign to...')->icon('heroicon-o-user-plus')
                    ->visible(fn () => (bool) auth()->user()?->can('enquiries.assign'))
                    ->schema([Select::make('owner_id')->label('Person')->options(fn () => User::counsellors()->pluck('name', 'id'))->searchable()->required()])
                    ->action(function (Collection $records, array $data) {
                        abort_unless(auth()->user()->can('enquiries.assign'), 403);
                        foreach ($records as $enquiry) {
                            static::setOwner($enquiry, User::find($data['owner_id']));
                        }
                        Notification::make()->title($records->count().' '.str('enquiry')->plural($records->count()).' assigned')->success()->send();
                    })->deselectRecordsAfterCompletion(),
                BulkAction::make('close')->label('Close')->icon('heroicon-o-check')->color('gray')->requiresConfirmation()
                    ->visible(fn () => (bool) auth()->user()?->can('enquiries.assign'))
                    ->action(function (Collection $records) {
                        abort_unless(auth()->user()->can('enquiries.assign'), 403);
                        foreach ($records as $enquiry) {
                            $before = $enquiry->only(['status', 'owner_id']);
                            $enquiry->forceFill(['status' => EnquiryStatus::Closed])->save();
                            AuditLog::record('enquiry.updated', $enquiry, $before, $enquiry->only(['status', 'owner_id']));
                        }
                        Notification::make()->title($records->count().' closed')->success()->send();
                    })->deselectRecordsAfterCompletion(),
            ]),
        ])->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [MessagesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListEnquiries::route('/'), 'edit' => EditEnquiry::route('/{record}/edit')];
    }
}
