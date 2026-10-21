<?php

namespace App\Enums;

enum ScanStatus : string
{
    case Pending = 'pending';
    case Available = 'available';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending scan',
            self::Available => 'Available',
            self::Rejected => 'Rejected',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}

