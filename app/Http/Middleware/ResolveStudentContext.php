<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Partner\AgentStudentController;
use App\Models\User;
use App\Support\StudentContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides whose application pages are being used. On student routes it is the signed-in student. On agent routes
 * (partner/students/{student}) it is that student, and only if the signed-in agent looks after them; anyone else gets 403.
 */
class ResolveStudentContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $student = $request->route('student');
        if (is_string($student)) {
            $student = User::where('public_id', $student)->firstOrFail(); // route binding may not have run yet
        }
        if ($student instanceof User) {
            abort_unless($user->manages($student), 403);
            $context = new StudentContext($student, $user);
            // The shared application controllers take the plan, not the student, so the extra route parameter must not shift their arguments.
            if ($request->route()->getControllerClass() !== AgentStudentController::class) {
                $request->route()->forgetParameter('student');
            }
        } else {
            $context = new StudentContext($user);
        }
        app()->instance(StudentContext::class, $context);
        $request->attributes->set('student', $context->student);

        return $next($request);
    }
}
