<?php

namespace App\Domain\Documents;

use App\Models\Invoice;
use App\Models\PaymentOption;
use TCPDF;

/** An invoice (while it is open) or a receipt (once it is paid) as a one-page PDF with the SAS letterhead. */
class InvoicePdf
{
    public function number(Invoice $invoice): string
    {
        return 'SAS-'.($invoice->status === 'paid' ? 'RCT' : 'INV').'-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
    }

    public function filename(Invoice $invoice): string
    {
        return $this->number($invoice).'.pdf';
    }

    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['serviceCase', 'student', 'paymentOption']);
        $paid = $invoice->status === 'paid';
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(18, 16, 18);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->SetTitle($this->number($invoice));
        $pdf->SetAuthor(config('sas.brand.name'));
        $pdf->AddPage();

        $logo = public_path('images/sas-logo.png');
        is_file($logo) && $pdf->Image($logo, 18, 14, 62, 0, 'PNG');
        $pdf->SetFont('dejavusans', 'B', 20);
        $pdf->SetTextColor(11, 74, 143);
        $pdf->SetXY(100, 16);
        $pdf->Cell(92, 10, $paid ? 'RECEIPT' : 'INVOICE', 0, 1, 'R');
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetX(100);
        $pdf->Cell(92, 5, 'No. '.$this->number($invoice), 0, 1, 'R');
        $pdf->SetX(100);
        $pdf->Cell(92, 5, 'Date: '.($paid ? $invoice->paid_at : $invoice->issued_at)?->format('j F Y'), 0, 1, 'R');

        $pdf->SetY(52);
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->Cell(0, 5, $paid ? 'Received from' : 'Billed to', 0, 1);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->MultiCell(0, 5, trim(($invoice->student?->name ?? '')."\n".($invoice->student?->email ?? '')), 0, 'L');

        $pdf->Ln(6);
        $pdf->SetFillColor(236, 236, 236);
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->Cell(112, 8, 'Description', 0, 0, 'L', true);
        $pdf->Cell(60, 8, 'Amount', 0, 1, 'R', true);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->MultiCell(112, 9, ($invoice->description ?: (Invoice::KINDS[$invoice->kind] ?? $invoice->kind))."\n".'Case: '.($invoice->serviceCase?->title ?? '-'), 0, 'L', false, 0);
        $pdf->Cell(60, 9, $invoice->amount(), 0, 1, 'R');
        $pdf->Ln(4);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
        $pdf->Ln(3);
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(112, 8, $paid ? 'Total received' : 'Total due', 0, 0, 'L');
        $pdf->Cell(60, 8, $invoice->amount(), 0, 1, 'R');

        $pdf->Ln(6);
        $pdf->SetFont('dejavusans', '', 9);
        $lines = [];
        if ($paid) {
            $lines[] = 'Paid on '.$invoice->paid_at->format('j F Y');
            $invoice->paymentOption && $lines[] = 'Paid by: '.$invoice->paymentOption->name;
            $invoice->payment_reference && $lines[] = 'Payment reference: '.$invoice->payment_reference;
        } elseif ($invoice->status === 'void') {
            $lines[] = 'This invoice was cancelled. You do not need to pay it.';
        } else {
            $invoice->due_on && $lines[] = 'Please pay by '.$invoice->due_on->format('j F Y').'.';
            $lines[] = 'How to pay:';
            foreach (PaymentOption::available()->get() as $option) {
                $lines[] = '- '.$option->name.($option->instructions ? ': '.$option->instructions : '').($option->link_url ? ' '.$option->link_url : '');
            }
        }
        if ($invoice->refund_status !== 'none' && $invoice->refund_cents > 0) {
            $lines[] = 'Refund: '.$invoice->refundAmount().($invoice->refund_status === 'paid' ? ' paid on '.$invoice->refunded_at?->format('j F Y') : ' is being processed').'.';
        }
        $pdf->MultiCell(0, 5, implode("\n", $lines), 0, 'L');

        $pdf->SetY(-34);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->MultiCell(0, 4, config('sas.brand.name').' · '.config('sas.contact_email').' · '.config('sas.contact_phone')."\nThis document records the SAS service fee only. It is not a scholarship award or a university fee.", 0, 'C');

        return $pdf->Output('', 'S');
    }
}
