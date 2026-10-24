<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Editorial\AuditLog;
use App\Enums\Role;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /** Only the super admin adds staff. No password is typed: the person gets an email link to choose their own. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStaff')
                ->label('Add staff member')
                ->icon(Heroicon::OutlinedUserPlus)
                ->visible(fn () => (bool) auth()->user()?->can('roles.manage'))
                ->modalDescription('The person receives an email with a link to choose their own password. Each person should have their own account; do not share one login.')
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('email')->label('Email (this is their sign-in name)')->email()->required()->maxLength(255)
                        ->dehydrateStateUsing(fn ($state) => Str::lower(trim((string) $state)))
                        ->rule(fn () => fn (string $attribute, $value, \Closure $fail) => User::whereRaw('lower(email) = ?', [Str::lower(trim((string) $value))])->exists() ? $fail('This email already has an account.') : null),
                    Select::make('role')->options(array_intersect_key(Role::staffOptions(), array_flip([Role::Administrator->value, Role::Advisor->value])))->required()
                        ->helperText('A further super admin can only be added from the server (php artisan sas:make-staff), so the owner role is never handed out by accident.'),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->can('roles.manage'), 403);
                    abort_unless(in_array($data['role'], [Role::Administrator->value, Role::Advisor->value], true), 422);

                    $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Str::random(40)]);
                    $user->forceFill(['email_verified_at' => now()])->save();
                    $user->userRoles()->create(['role' => $data['role'], 'granted_by' => auth()->id()]);
                    AuditLog::record('user.staff_added', $user, null, ['role' => $data['role']]);

                    $sent = Password::sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT;
                    Notification::make()
                        ->title($user->name.' was added as '.Role::from($data['role'])->label())
                        ->body($sent ? 'An email with a link to choose a password was sent to '.$user->email.'.' : 'The email could not be sent. Open the person and use "Send password reset link" once email is set up, or set a password there.')
                        ->color($sent ? 'success' : 'warning')
                        ->send();
                }),
        ];
    }
}
