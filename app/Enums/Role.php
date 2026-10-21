<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Administrator = 'administrator';
    case ServiceManager = 'service_manager';
    case Advisor = 'advisor';
    case Specialist = 'specialist';
    case Editor = 'editor';
    case Reviewer = 'reviewer';
    case Finance = 'finance';
    case PartnerManager = 'partner_manager';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrator',
            self::Administrator => 'Administrator',
            self::ServiceManager => 'Service manager',
            self::Advisor => 'Advisor',
            self::Specialist => 'Writer or document specialist',
            self::Editor => 'Editor',
            self::Reviewer => 'Reviewer',
            self::Finance => 'Finance',
            self::PartnerManager => 'Partner manager',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
