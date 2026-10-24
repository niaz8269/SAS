<?php

namespace App\Http\Controllers\Partner;

use App\Domain\Agents\AgentMessaging;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AgentMessageController extends Controller
{
    public function store(Request $request, AgentMessaging $messaging)
    {
        $student = $request->attributes->get('student'); // set by ResolveStudentContext, which already checked the agency looks after this student
        abort_unless($student && $request->user()->manages($student), 403);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:2000']]);
        $messaging->fromAgent($student, $request->user(), $data['body']);

        return redirect()->route('partner.student.show', $student->public_id)->withFragment('sas')->with('status', 'Sent to SAS. You get an email when they answer.');
    }
}
