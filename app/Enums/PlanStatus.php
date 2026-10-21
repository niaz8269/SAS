<?php

namespace App\Enums;

enum PlanStatus: string
{
    case Interested = 'interested';
    case Preparing = 'preparing';
    case ReadyForReview = 'ready_for_review';
    case Submitted = 'submitted';
    case AwaitingOutcome = 'awaiting_outcome';
    case Awarded = 'awarded';
    case Unsuccessful = 'unsuccessful';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Interested => 'Interested',
            self::Preparing => 'Preparing',
            self::ReadyForReview => 'Ready for review',
            self::Submitted => 'Submitted (reported)',
            self::AwaitingOutcome => 'Awaiting outcome',
            self::Awarded => 'Awarded',
            self::Unsuccessful => 'Unsuccessful',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
