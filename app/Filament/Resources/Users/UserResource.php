<?php

namespace App\Filament\Resources\Users;

use App\Domain\Editorial\AuditLog;
use App\Enums\Role;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/** AUTH-05: managing users (users.manage) is separate from granting roles (roles.manage, super administrators only). */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('users.manage');
    }

    public static function canCreate(): bool
    {
        return false; // staff accounts are created with `php artisan sas:make-staff` so credentials are never typed into a form
    }

    public static function canEdit(Model $record): bool
    {
        $actor = auth()->user();
        if (! $actor?->can('users.manage')) {
            return false;
        }

        // Only a super administrator may touch another super administrator.
        return ! $record->hasRole(Role::SuperAdmin) || $actor->hasRole(Role::SuperAdmin);
    }

    public static function canDelete(Model $record): bool
    {
        return false; // a row is never erased from the Admin (SCHEMA-05); "Delete user" archives the account, see canArchive()
    }

    /** Only the super admin deletes (archives) users, and never themselves. */
    public static function canArchive(Model $record): bool
    {
        $actor = auth()->user();

        return (bool) $actor?->can('roles.manage') && $record->getKey() !== $actor->getKey() && ! $record->isArchived();
    }

    /** The "Delete user" action, shared by the list and the edit page. The account is archived, never erased. */
    public static function archiveAction(): Action
    {
        return Action::make('archiveUser')
            ->label('Delete user')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (Model $record) => self::canArchive($record))
            ->requiresConfirmation()
            ->modalHeading('Delete this user?')
            ->modalDescription('They are signed out at once and can never sign in again, and all their roles are removed. Their leads, applications, reviews and history stay as they are. You can bring the account back later by setting its status to Active and granting roles again.')
            ->modalSubmitActionLabel('Delete user')
            ->action(function (Model $record) {
                abort_unless(self::canArchive($record), 403);
                $roles = $record->userRoles->pluck('role')->all();
                $record->archive();
                AuditLog::record('user.archived', $record, ['status' => 'active', 'roles' => $roles], ['status' => 'archived']);
                Notification::make()->title($record->name.' was deleted')->success()->send();
            });
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('userRoles');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->label('Email (sign-in name)')->email()->required()->maxLength(255)
                    ->disabled(fn () => ! auth()->user()->can('roles.manage'))->dehydrated(fn () => auth()->user()->can('roles.manage'))
                    ->dehydrateStateUsing(fn ($state) => Str::lower(trim((string) $state)))
                    ->rule(fn (?Model $record) => fn (string $attribute, $value, \Closure $fail) => User::whereRaw('lower(email) = ?', [Str::lower(trim((string) $value))])->where('id', '!=', $record?->getKey())->exists() ? $fail('This email already has an account.') : null)
                    ->helperText('Only the super admin can change it. People sign in with this address.'),
                TextInput::make('phone'),
                Select::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended (all sessions end immediately)', 'archived' => 'Deleted (cannot sign in; set to Active to restore)'])->required(),
            ]),
            Section::make('Password')->description('Leave empty to keep the current password. Or use "Send password reset link" at the top so the person chooses their own. A new password signs the person out of every other device.')
                ->columns(2)->visible(fn () => (bool) auth()->user()->can('roles.manage'))->schema([
                    TextInput::make('new_password')->label('New password')->password()->revealable()->autocomplete('new-password')
                        ->rule(PasswordRule::defaults())->confirmed()->dehydrated(),
                    TextInput::make('new_password_confirmation')->label('Repeat the new password')->password()->revealable()->autocomplete('new-password')->dehydrated(false),
                ]),
            Section::make('Staff roles')->description('Only super administrators can grant or remove roles. Staff must complete multi-factor set-up before using the admin.')->schema([
                CheckboxList::make('roles')->options(Role::staffOptions())->columns(3)
                    ->disabled(fn () => ! auth()->user()->can('roles.manage'))->dehydrated(fn () => auth()->user()->can('roles.manage')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('roles')->label('Roles')->state(fn (User $u) => $u->userRoles->pluck('role')->map(fn ($r) => Role::tryFrom($r)?->label() ?? $r)->all())->badge()->placeholder('Student'),
            TextColumn::make('status')->badge()->formatStateUsing(fn ($s) => $s === 'archived' ? 'Deleted' : ucfirst((string) $s))->color(fn ($s) => $s === 'active' ? 'success' : ($s === 'archived' ? 'gray' : 'danger')),
            IconColumn::make('email_verified_at')->label('Verified')->boolean()->getStateUsing(fn (User $u) => $u->email_verified_at !== null),
            IconColumn::make('mfa')->label('MFA')->boolean()->getStateUsing(fn (User $u) => filled($u->app_authentication_secret)),
            TextColumn::make('created_at')->date()->sortable(),
        ])->filters([
            SelectFilter::make('role')->options(Role::staffOptions())
                ->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereHas('userRoles', fn ($r) => $r->where('role', $data['value'])) : $query),
            SelectFilter::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended']),
            TernaryFilter::make('archived')->label('Deleted users')->placeholder('All')->trueLabel('Deleted only')->falseLabel('Not deleted')->default(false)
                ->queries(true: fn (Builder $q) => $q->where('status', 'archived'), false: fn (Builder $q) => $q->where('status', '!=', 'archived'), blank: fn (Builder $q) => $q),
        ])->recordActions([EditAction::make(), self::archiveAction()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/'), 'edit' => EditUser::route('/{record}/edit')];
    }
}
