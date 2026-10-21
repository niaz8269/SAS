<?php

namespace App\Enums;

enum DeadlineType : string
{
    case Exact = 'exact';
    case DateOnly = 'date_only';
    case Rolling = 'rolling';
    case NotAnnounced = 'not_announced';
    case RouteDependent = 'route_dependent';

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Exact time',
            self::DateOnly => 'Date only',
            self::Rolling => 'Rolling',
            self::NotAnnounced => 'Not announced',
            self::RouteDependent => 'Varies by route',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}

