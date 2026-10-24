<?php

namespace App\Http\Controllers;

use App\Domain\Documents\InvoicePdf;
use App\Models\Invoice;
use Illuminate\Http\Request;

/** An invoice or receipt as a PDF, for the student it belongs to and for staff who may read it. */
class InvoicePdfController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, InvoicePdf $pdf)
    {
        $this->authorize('view', $invoice);

        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$pdf->filename($invoice).'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
