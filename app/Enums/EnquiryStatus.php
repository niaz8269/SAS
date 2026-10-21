<?php

namespace App\Enums;

enum EnquiryStatus : string
{
    case New = 'new';
    case Assigned = 'assigned';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case QuoteIssued = 'quote_issued';
    case Converted = 'converted';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Assigned => 'Assigned',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::QuoteIssued => 'Quote issued',
            self::Converted => 'Converted',
            self::Closed => 'Closed',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}

