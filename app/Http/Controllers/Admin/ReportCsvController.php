<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\BusinessReport;
use App\Domain\Editorial\AuditLog;
use App\Http\Controllers\Controller;
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

        $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;

        return response()->streamDownload(function () use ($s, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads it as UTF-8
            fputcsv($out, $s['headers']);
            foreach ($s['rows'] as $row) {
                fputcsv($out, array_map($safe, $row));
            }
            fclose($out);
        }, 'sas-'.$section.'-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
