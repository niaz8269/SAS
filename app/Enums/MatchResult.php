<?php

namespace App\Enums;

enum MatchResult: string
{
    case PotentialMatch = 'potential_match';
    case CriterionNotMet = 'criterion_not_met';
    case MoreInformation = 'more_information';

    public function label(): string
    {
        return match ($this) {
            self::PotentialMatch => 'Potential match',
            self::CriterionNotMet => 'Known criterion not met',
            self::MoreInformation => 'More information needed',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
