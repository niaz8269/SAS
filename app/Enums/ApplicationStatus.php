<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Upcoming = 'upcoming';
    case Open = 'open';
    case Closed = 'closed';
    case Rolling = 'rolling';
    case DeadlineUnconfirmed = 'deadline_unconfirmed';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Rolling => 'Rolling admissions',
            self::DeadlineUnconfirmed => 'Deadline unconfirmed',
            self::Suspended => 'Suspended',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
