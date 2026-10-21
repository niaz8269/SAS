<?php

namespace App\Enums;

enum PublicationState : string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::ChangesRequested => 'Changes requested',
            self::Approved => 'Approved',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}

