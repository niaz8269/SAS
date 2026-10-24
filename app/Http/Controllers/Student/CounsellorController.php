<?php

namespace App\Http\Controllers\Student;

use App\Domain\Counselling\Counselling;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** The student's conversation with their SAS counsellor. */
class CounsellorController extends Controller
{
    public function show(Request $request)
    {
        $student = $request->user();

        return view('student.counsellor', [
            'counsellor' => $student->counsellor, 'messages' => $student->counsellorMessages()->with('author')->oldest('id')->get(),
        ]);
    }

    public function store(Request $request, Counselling $counselling)
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:2000']]);
        $counselling->message($request->user(), $request->user(), $data['body']);

        return redirect()->route('student.counsellor')->withFragment('end')->with('status', 'Message sent. Your counsellor will reply here.');
    }
}
