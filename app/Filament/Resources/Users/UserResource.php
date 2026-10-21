<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
        return false; // erasure follows the retention policy and is not a casual admin action (SCHEMA-05)
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
                TextInput::make('email')->disabled()->dehydrated(false),
                TextInput::make('phone'),
                Select::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended (all sessions end immediately)'])->required(),
            ]),
            Section::make('Staff roles')->description('Only super administrators can grant or remove roles. Staff must complete multi-factor set-up before using the admin.')->schema([
                CheckboxList::make('roles')->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->label()])->all())->columns(3)
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
            TextColumn::make('status')->badge()->color(fn ($s) => $s === 'active' ? 'success' : 'danger'),
            IconColumn::make('email_verified_at')->label('Verified')->boolean()->getStateUsing(fn (User $u) => $u->email_verified_at !== null),
            IconColumn::make('mfa')->label('MFA')->boolean()->getStateUsing(fn (User $u) => filled($u->app_authentication_secret)),
            TextColumn::make('created_at')->date()->sortable(),
        ])->filters([
            SelectFilter::make('role')->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->label()])->all())
                ->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereHas('userRoles', fn ($r) => $r->where('role', $data['value'])) : $query),
            SelectFilter::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended']),
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/'), 'edit' => EditUser::route('/{record}/edit')];
    }
}
