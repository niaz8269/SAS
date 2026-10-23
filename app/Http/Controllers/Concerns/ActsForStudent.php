<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Support\StudentContext;
use Illuminate\Http\Request;

/** For the application pages a student uses and an agent uses on behalf of a student they look after (see ResolveStudentContext). */
trait ActsForStudent
{
    /** The student whose application this is: the signed-in student, or the managed student an agent is working on. */
    protected function student(Request $request): User
    {
        return $request->attributes->get('student') ?? $request->user();
    }

    protected function ctx(Request $request): StudentContext
    {
        return app(StudentContext::class);
    }
}
