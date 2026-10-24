<?php

namespace App\Domain\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use TCPDF;

/** Several phone photos of a document (a JPEG or PNG each) become one A4 PDF, one photo per page, in the order given. */
class PhotosToPdf
{
    /** @param list<UploadedFile> $photos */
    public function make(array $photos, string $name): UploadedFile
    {
        if (count($photos) < 2) {
            throw new InvalidArgumentException('Choose at least two photos to combine.');
        }
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(8, 8, 8);
        $pdf->SetAutoPageBreak(false);
        $temp = [];
        foreach ($photos as $photo) {
            $ext = strtolower($photo->getClientOriginalExtension());
            $kind = (new DocumentService)->sniff($photo->getRealPath(), $ext);
            if (! in_array($kind, ['image/jpeg', 'image/png'], true)) {
                throw new InvalidArgumentException('Only JPEG or PNG photos can be combined into one PDF.');
            }
            if (($dim = @getimagesize($photo->getRealPath())) && $dim[0] * $dim[1] > DocumentService::MAX_PIXELS) {
                throw new InvalidArgumentException('One of the photos is far larger than a document photo needs. Please reduce it and try again.');
            }
            $path = tempnam(sys_get_temp_dir(), 'sas-photo-').($kind === 'image/png' ? '.png' : '.jpg');
            File::copy($photo->getRealPath(), $path);
            $temp[] = $path;
            $pdf->AddPage();
            $pdf->Image($path, 8, 8, 194, 281, '', '', '', true, 200, '', false, false, 0, 'CM', false, false); // fitted inside the page margins, centred
        }
        $out = tempnam(sys_get_temp_dir(), 'sas-pdf-');
        File::put($out, $pdf->Output('', 'S'));
        foreach ($temp as $path) {
            File::delete($path);
        }

        return new UploadedFile($out, $name, 'application/pdf', null, true);
    }
}
