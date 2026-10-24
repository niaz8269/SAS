<?php

namespace App\Domain\Applications;

use App\Domain\Documents\DocumentService;
use App\Enums\ScanStatus;
use App\Models\ApplicationPlan;
use App\Models\Document;
use Illuminate\Support\Carbon;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * The student's application as the SAS application form (resources/document-templates/sas-application-form.pdf): the blank pages with the
 * student's answers, application number and ticked supporting documents written on top. Positions come from sas-application-form.map.json.
 */
class ApplicationFormPdf
{
    private const TEMPLATE = 'document-templates/sas-application-form.pdf';

    private const MAP = 'document-templates/sas-application-form.map.json';

    /** Supporting-documents list on page 5: document type key => top of its line. A letter of recommendation fills Reference I, then Reference II. */
    private const TICKS = [
        'photo' => 64, 'degree_certificate' => 82, 'transcript' => 113, 'study_plan' => 130, 'passport' => 183, 'medical' => 200,
        'publications' => 218, 'portfolio' => 235, 'chinese_language' => 266, 'english_language' => 283, 'csca' => 301, 'pre_admission_letter' => 318, 'police_clearance' => 336,
    ];

    /** The four degree boxes under "Certificates of Highest Education". */
    private const DEGREE_BOXES = ['Bachelor' => 121.6, 'Master' => 181.6, 'Doctoral' => 238.4, 'Other' => 284.0];

    private const INK = [16, 36, 110];

    public function __construct(private ApplicationFormService $forms, private DocumentService $documents) {}

    public function render(ApplicationPlan $plan): string
    {
        $plan->loadMissing(['cycle.programme', 'degreeLevel', 'user.managedBy']);
        $user = $plan->user;
        $data = $this->forms->formFor($user)->data ?? [];
        $held = Document::where('user_id', $user->id)->with('currentVersion')->get()->filter(fn ($d) => $d->currentVersion && $d->currentVersion->scan_status !== ScanStatus::Rejected)->groupBy('type');
        $map = json_decode((string) file_get_contents(resource_path(self::MAP)), true)['fields'];

        $pdf = new Fpdi('P', 'pt', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(config('sas.brand.name'));
        $pdf->SetAuthor(config('sas.brand.name'));
        $pdf->SetTitle('SAS application form '.$plan->application_no);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetTextColor(...self::INK);

        $template = base_path('resources/'.self::TEMPLATE);
        $pages = $pdf->setSourceFile($template);
        for ($n = 1; $n <= $pages; $n++) {
            $pdf->AddPage();
            $pdf->useTemplate($pdf->importPage($n), 0, 0, 595, 842);
            $this->footer($pdf, $plan);
            match ($n) {
                1 => $this->first($pdf, $plan, $data, $held),
                2 => $this->moreEmployment($pdf, $data),
                3 => $this->moreLanguages($pdf, $data),
                5 => $this->ticks($pdf, $data, $held),
                6 => $this->declaration($pdf, $data),
                default => null,
            };
            foreach (array_filter($map, fn ($f) => $f['p'] === $n) as $f) {
                $value = $this->value($f['src'], $data, $plan);
                if ($value !== '') {
                    $this->write($pdf, $value, $f['x'], $f['y'], $f['w'], $f['h']);
                }
            }
        }

        return $pdf->Output('', 'S');
    }

    public function filename(ApplicationPlan $plan): string
    {
        return 'SAS-application-form-'.$plan->application_no.'.pdf';
    }

    /** @param array<string,mixed> $data */
    private function value(string $src, array $data, ApplicationPlan $plan): string
    {
        $raw = match (true) {
            $src === '@agency' => $plan->user->managedBy?->name,
            $src === '@language' => $plan->study_language,
            str_starts_with($src, '@chinese.') => $this->chinese($data, substr($src, 9)),
            str_starts_with($src, '@period.') => $this->period($data, substr($src, 8)),
            str_contains($src, '.') => data_get($data, $src),
            default => $data[$src] ?? null,
        };
        $raw = is_scalar($raw) ? (string) $raw : '';

        return match ($raw) {
            'yes' => 'Yes', 'no' => 'No', default => trim($raw),
        };
    }

    /** The dates a student studied somewhere, as "15 Sep 2019 to 30 Jun 2023". Older records that only have free text still show it. */
    private function period(array $data, string $where): ?string
    {
        [$from, $to, $legacy] = $where === 'edu'
            ? [$data['edu_from'] ?? null, $data['edu_to'] ?? null, $data['edu_years'] ?? null]
            : [data_get($data, $where.'.from'), data_get($data, $where.'.to'), data_get($data, $where.'.years') ?? data_get($data, $where.'.duration')];
        $show = function ($d) {
            try {
                return Carbon::parse($d)->format('j M Y');
            } catch (\Throwable) {
                return (string) $d;
            }
        };
        if (blank($from) && blank($to)) {
            return $legacy;
        }

        // A job with a start and no end is one the student still has.
        $end = filled($to) ? $show($to) : (str_starts_with($where, 'employment') && filled($from) ? 'present' : '');

        return trim((filled($from) ? $show($from) : '').' to '.$end, ' ');
    }

    /** The Chinese row of "Other languages you know", for the form's Chinese proficiency and HSK boxes. */
    private function chinese(array $data, string $part): ?string
    {
        $row = collect($data['other_languages'] ?? [])->first(fn ($r) => preg_match('/chin|mandarin/i', (string) ($r['language'] ?? '')));
        if (! $row) {
            return null;
        }

        return match ($part) {
            'level' => $row['level'] ?? null,
            'holder' => filled($row['certificate'] ?? null) ? 'Yes' : 'No',
            'score' => $row['score'] ?? null,
            'date' => $row['date'] ?? null,
            default => null,
        };
    }

    private function first(Fpdi $pdf, ApplicationPlan $plan, array $data, $held): void
    {
        $digits = substr(preg_replace('/\D/', '', $plan->application_no), -10);
        $cell = (541 - 439) / 12;
        foreach (str_split($digits) as $i => $char) {
            $this->font($pdf, $char, 7);
            $pdf->SetXY(439 + $cell * (2 + $i), 64.2);
            $pdf->Cell($cell, 9, $char, 0, 0, 'C');
        }
        $this->write($pdf, $plan->cycle->displayName(), 376, 75.5, 60, 11, 6.5);
        $this->write($pdf, (string) ($plan->degreeLevel?->name ?? ''), 482, 75.5, 56, 11, 6.5);
        $this->write($pdf, (string) ($data['major'] ?? ''), 487, 88.5, 52, 11, 6.5);

        // The photo box beside the first rows: the student's passport-style photo, when it is an image they uploaded.
        $photo = $held->get('photo', collect())->first(fn ($d) => $d->currentVersion->isDownloadable() && str_starts_with((string) $d->currentVersion->mime, 'image/'));
        if ($photo) {
            try {
                $bytes = $this->documents->disk()->get($this->documents->pathFor($photo->currentVersion));
                $pdf->Image('@'.$bytes, 438, 227, 124, 124, '', '', '', false, 300, '', false, false, 0, 'CM', false, false);
            } catch (\Throwable) {
                // an unreadable photo is simply left out
            }
        }
    }

    /** The template has room for one job; the rest of the student's employment history is listed under it. */
    private function moreEmployment(Fpdi $pdf, array $data): void
    {
        $rows = collect($data['employment'] ?? [])->slice(1)->filter()->values();
        if ($rows->isEmpty()) {
            return;
        }
        $this->font($pdf, 'Further employment', 9, true);
        $pdf->SetXY(29, 636);
        $pdf->Cell(300, 12, 'Further employment', 0, 0, 'L');
        foreach ($rows as $i => $r) {
            $this->write($pdf, collect([$r['employer'] ?? null, $this->period(['employment' => [$r]], 'employment.0'), $r['position'] ?? null])->filter()->implode('  |  '), 29, 652 + $i * 16, 532, 14);
        }
    }

    /** Other languages besides Chinese, which has its own boxes. */
    private function moreLanguages(Fpdi $pdf, array $data): void
    {
        $rows = collect($data['other_languages'] ?? [])->reject(fn ($r) => preg_match('/chin|mandarin/i', (string) ($r['language'] ?? '')))->filter()->values();
        if ($rows->isEmpty()) {
            return;
        }
        $this->font($pdf, 'Other languages', 9, true);
        $pdf->SetXY(29, 452);
        $pdf->Cell(300, 12, 'Other languages', 0, 0, 'L');
        foreach ($rows as $i => $r) {
            $line = collect([$r['language'] ?? null, $r['level'] ?? null, $r['certificate'] ?? null, $r['score'] ?? null, $r['date'] ?? null])->filter()->implode('  |  ');
            $this->write($pdf, $line, 29, 468 + $i * 16, 532, 14);
        }
    }

    /** Ticks the supporting documents the student has uploaded and the degree of the highest education. */
    private function ticks(Fpdi $pdf, array $data, $held): void
    {
        $has = fn (string $type) => $held->get($type, collect())->count();
        foreach (self::TICKS as $type => $top) {
            if ($has($type) > 0) {
                $this->tick($pdf, 26, $top + 0.6);
            }
        }
        $letters = $has('recommendation');
        if ($letters >= 1) {
            $this->tick($pdf, 26, 148.6);
        }
        if ($letters >= 2) {
            $this->tick($pdf, 26, 165.6);
        }
        if (isset(self::DEGREE_BOXES[$data['edu_level'] ?? ''])) {
            $this->tick($pdf, self::DEGREE_BOXES[$data['edu_level']], 95.2);
        }
    }

    private function declaration(Fpdi $pdf, array $data): void
    {
        if (blank($data['_declared_at'] ?? null)) {
            return;
        }
        $this->tick($pdf, 26, 485.6);
        $this->write($pdf, 'Declared on '.Carbon::parse($data['_declared_at'])->format('j F Y'), 36, 526, 300, 14, 9);
    }

    private function footer(Fpdi $pdf, ApplicationPlan $plan): void
    {
        $this->write($pdf, $plan->application_no, 104, 826.2, 160, 10, 8);
        $this->write($pdf, now()->format('j M Y H:i'), 510, 826.2, 85, 10, 8);
    }

    private function tick(Fpdi $pdf, float $x, float $y): void
    {
        $pdf->SetFont('zapfdingbats', '', 8);
        $pdf->SetXY($x - 0.2, $y - 0.1);
        $pdf->Cell(8, 8.5, '4', 0, 0, 'C');
    }

    /** One answer in its box: shrinks to fit and wraps in the taller boxes. */
    private function write(Fpdi $pdf, string $text, float $x, float $y, float $w, float $h, float $size = 9): void
    {
        if ($text === '') {
            return;
        }
        $this->font($pdf, $text, $size);
        $pdf->MultiCell($w, $h, $text, 0, 'L', false, 0, $x, $y, true, 0, false, true, $h, 'T', true);
    }

    /** DejaVu Sans covers Latin, Greek, Cyrillic and Arabic; Chinese names use the CJK face the PDF viewer supplies. */
    private function font(Fpdi $pdf, string $text, float $size, bool $bold = false): void
    {
        preg_match('/\p{Han}/u', $text) ? $pdf->SetFont('cid0cs', '', $size) : $pdf->SetFont('dejavusans', $bold ? 'B' : '', $size);
    }
}
