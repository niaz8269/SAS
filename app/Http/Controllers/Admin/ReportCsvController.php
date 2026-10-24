<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\BusinessReport;
use App\Domain\Editorial\AuditLog;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** One report section as a CSV file, for admins. Cells that start with a formula character are neutralised so a spreadsheet never runs them. */
class ReportCsvController extends Controller
{
    public function __invoke(Request $request, string $section, BusinessReport $report): StreamedResponse
    {
        abort_unless($request->user()->isStaff() && $request->user()->hasPermission('reports.view'), 403);
        abort_unless(in_array($section, BusinessReport::SECTIONS, true), 404);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = Carbon::parse($data['from'] ?? now()->subDays(29));
        $to = Carbon::parse($data['to'] ?? now());
        $s = $report->section($section, $from, $to);
        AuditLog::record('report.exported', null, null, ['section' => $section], null, $request->user());

        return Csv::download('sas-'.$section.'-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', $s['headers'], $s['rows']);
    }
}
