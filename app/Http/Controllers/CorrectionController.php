<?php

namespace App\Http\Controllers;

use App\Models\CorrectionReport;
use App\Models\ScholarshipCycle;
use Illuminate\Http\Request;

class CorrectionController extends Controller
{
    public function create(Request $request)
    {
        return view('public.correction', [
            'cycle' => $request->filled('cycle') ? ScholarshipCycle::visible()->with('programme')->find($request->query('cycle')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cycle_id' => ['nullable', 'integer', 'exists:scholarship_cycles,id'],
            'page_url' => ['nullable', 'url', 'max:500'],
            'reporter_name' => ['nullable', 'string', 'max:120'],
            'reporter_email' => ['nullable', 'email', 'max:190'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['nullable', 'max:0'],
        ]);
        if (filled($request->input('website'))) {
            return redirect()->route('home');
        }

        $report = CorrectionReport::create(collect($data)->except('website')->all());

        // DATA-04: a correction report triggers an immediate review of the cycle and pulls it from promoted placements.
        if ($report->cycle_id) {
            ScholarshipCycle::whereKey($report->cycle_id)->update(['review_due_at' => now()]);
        }

        return redirect()->route('home')->with('status', 'Thank you. Our editors will check the official source and update the listing if needed.');
    }
}
