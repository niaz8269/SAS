<?php

namespace App\Domain\Editorial;

use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader;

/** Turns the first sheet of an Excel file into a CSV file, so every importer reads one format. Formulas are read as the value Excel stored. */
class SpreadsheetFile
{
    /** @return string path of a new temporary CSV file (the caller deletes it) */
    public static function toCsv(string $xlsxPath): string
    {
        $csv = tempnam(sys_get_temp_dir(), 'sas-xlsx-');
        $out = fopen($csv, 'w');
        $reader = new Reader;
        $reader->open($xlsxPath);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    fputcsv($out, array_map(self::cell(...), $row->toArray()), ',', '"', '');
                }
                break; // the sheet to fill in is always the first one
            }
        } finally {
            $reader->close();
            fclose($out);
        }

        return $csv;
    }

    private static function cell(mixed $v): string
    {
        return match (true) {
            $v === null => '',
            $v instanceof DateTimeInterface => $v->format('H:i:s') === '00:00:00' ? $v->format('Y-m-d') : $v->format('Y-m-d H:i'),
            is_bool($v) => $v ? 'yes' : 'no',
            is_float($v) => rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.'),
            default => (string) $v,
        };
    }
}
