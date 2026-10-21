<?php

namespace App\Domain\Identity;

class Permissions
{
    /** @param list<string> $roleKeys */
    public static function roleKeysGrant(array $roleKeys, string $permission): bool
    {
        $map = config('sas.permissions', []);
        foreach ($roleKeys as $role) {
            $granted = $map[$role] ?? [];
            if (in_array('*', $granted, true) || in_array($permission, $granted, true)) {
                return true;
            }
        }

        return false;
    }
}
