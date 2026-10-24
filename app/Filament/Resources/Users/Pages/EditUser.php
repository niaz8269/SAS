<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Editorial\AuditLog;
use App\Enums\Role;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendResetLink')
                ->label('Send password reset link')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->visible(fn () => (bool) auth()->user()?->can('roles.manage'))
                ->requiresConfirmation()
                ->modalDescription('The person gets an email with a link to choose a new password. Their current password keeps working until they do.')
                ->action(function (): void {
                    abort_unless(UserResource::canEdit($this->getRecord()) && auth()->user()->can('roles.manage'), 403);
                    $sent = Password::sendResetLink(['email' => $this->getRecord()->email]) === Password::RESET_LINK_SENT;
                    AuditLog::record('user.reset_link_sent', $this->getRecord());
                    Notification::make()->title($sent ? 'Reset link sent to '.$this->getRecord()->email : 'The email could not be sent')->color($sent ? 'success' : 'warning')->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->getRecord()->userRoles->pluck('role')->all();

        return $data;
    }

    /** Roles, status, email and password are guarded or sensitive, so they are changed here explicitly, after authorisation, with an audit trail. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless(UserResource::canEdit($record), 403);

        $record->fill(['name' => $data['name'], 'phone' => $data['phone'] ?? null])->save();

        if ($actor->can('roles.manage')) {
            $email = $data['email'] ?? $record->email;
            if ($email !== $record->email) {
                $old = $record->email;
                $record->forceFill(['email' => $email, 'email_verified_at' => now()])->save();
                AuditLog::record('user.email_changed', $record, ['email' => $old], ['email' => $email]);
            }
            if (filled($data['new_password'] ?? null)) {
                $record->forceFill(['password' => $data['new_password']])->save();
                $other = DB::table('sessions')->where('user_id', $record->id);
                if ($record->id === $actor->id) {
                    $other->where('id', '!=', session()->getId());
                }
                $other->delete(); // a changed password signs the person out everywhere else
                AuditLog::record('user.password_set', $record); // the password itself is never logged
            }
        }

        if (($data['status'] ?? $record->status) !== $record->status) {
            abort_if($record->id === $actor->id, 422, 'You cannot change your own status.');
            if ($data['status'] === 'suspended') {
                $record->suspend(); // also ends every session
            } else {
                $record->forceFill(['status' => 'active', 'suspended_at' => null])->save();
            }
            AuditLog::record('user.status', $record, null, ['status' => $data['status']]);
        }

        if ($actor->can('roles.manage') && array_key_exists('roles', $data)) {
            $new = collect($data['roles'] ?? [])->filter(fn ($r) => in_array($r, config('sas.staff_roles'), true))->values();
            $old = $record->userRoles->pluck('role')->filter(fn ($r) => in_array($r, config('sas.staff_roles'), true))->values();
            if ($new->sort()->values()->all() !== $old->sort()->values()->all()) {
                abort_if($record->id === $actor->id && ! $new->contains(Role::SuperAdmin->value), 422, 'You cannot remove your own super administrator role.');
                $record->userRoles()->whereIn('role', config('sas.staff_roles'))->whereNotIn('role', $new)->delete(); // the agent role is not edited here
                foreach ($new as $role) {
                    $record->userRoles()->firstOrCreate(['role' => $role], ['granted_by' => $actor->id]);
                }
                AuditLog::record('user.roles_changed', $record, ['roles' => $old->all()], ['roles' => $new->all()]);
            }
        }

        return $record;
    }
}
