<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** A CSV download. A cell that starts with = + - @ (or a tab or return) is prefixed with an apostrophe so a spreadsheet never runs it as a formula. */
class Csv
{
    public static function safe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /** @param list<string> $headers  @param iterable<list<string|int|float|null>> $rows */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads it as UTF-8
            fputcsv($out, array_map([self::class, 'safe'], $headers));
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
