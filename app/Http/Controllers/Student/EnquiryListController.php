<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EnquiryListController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('student.enquiries', [
            'enquiries' => $request->user()->enquiries()->with('service')->latest()->get(),
            'consultations' => $request->user()->consultations()->with('service')->latest()->get(),
        ]);
    }
}
