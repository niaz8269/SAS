<?php

namespace App\Enums;

/**
 * The staff of SAS work in three roles: Counsellor (looks after students), Admin (runs the service and the website) and
 * Super admin (the owner: also manages who has which role). Agents sign in to their own portal with the partner role.
 * The counsellor's stored key stays "advisor" so existing records keep working.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Administrator = 'administrator';
    case Advisor = 'advisor';
    case PartnerManager = 'partner_manager';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Administrator => 'Admin',
            self::Advisor => 'Counsellor',
            self::PartnerManager => 'Partner manager',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<string,string> value => label, for the staff roles only */
    public static function staffOptions(): array
    {
        return collect(self::cases())->filter(fn (self $c) => in_array($c->value, config('sas.staff_roles'), true))->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }

    /** Roles whose people can be a student's counsellor or the owner of an enquiry: counsellors, and admins and the super admin who step in. */
    public static function counsellorKeys(): array
    {
        return [self::Advisor->value, self::Administrator->value, self::SuperAdmin->value];
    }
}
