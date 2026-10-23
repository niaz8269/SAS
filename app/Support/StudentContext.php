<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Whose application pages these are, and how to link between them. A student works on their own pages (student.*);
 * a recruiting agent works on a student they look after through the same pages under partner/students/{student} (partner.student.*).
 * Controllers ask for the student here instead of the signed-in user, and views build links with route().
 */
class StudentContext
{
    public function __construct(public readonly User $student, public readonly ?User $agent = null) {}

    public function isAgent(): bool
    {
        return $this->agent !== null;
    }

    /** The layout component for this context. */
    public function layout(): string
    {
        return $this->isAgent() ? 'layouts.partner' : 'layouts.student';
    }

    /** A link to a page of the application flow, for the student or for the agent working on that student. */
    public function route(string $name, mixed $params = []): string
    {
        $params = Arr::wrap($params);

        return $this->isAgent()
            ? route('partner.student.'.$name, ['student' => $this->student->public_id] + $params)
            : route('student.'.$name, $params);
    }
}
