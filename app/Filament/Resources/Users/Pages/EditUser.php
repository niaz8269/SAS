<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Editorial\AuditLog;
use App\Enums\Role;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->getRecord()->userRoles->pluck('role')->all();

        return $data;
    }

    /** Roles and status are guarded attributes, so they are changed here explicitly, after authorisation, with an audit trail. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless(UserResource::canEdit($record), 403);

        $record->fill(['name' => $data['name'], 'phone' => $data['phone'] ?? null])->save();

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
            $new = collect($data['roles'] ?? [])->filter(fn ($r) => Role::tryFrom($r))->values();
            $old = $record->userRoles->pluck('role');
            if ($new->sort()->values()->all() !== $old->sort()->values()->all()) {
                abort_if($record->id === $actor->id && ! $new->contains(Role::SuperAdmin->value), 422, 'You cannot remove your own super administrator role.');
                $record->userRoles()->whereNotIn('role', $new)->delete();
                foreach ($new as $role) {
                    $record->userRoles()->firstOrCreate(['role' => $role], ['granted_by' => $actor->id]);
                }
                AuditLog::record('user.roles_changed', $record, ['roles' => $old->all()], ['roles' => $new->all()]);
            }
        }

        return $record;
    }
}
