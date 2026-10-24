<?php

namespace App\Support;

/**
 * Text that a student, an agent or staff member typed, made safe to place inside an email line. Mail lines are read as Markdown, so
 * brackets would become links and bare web addresses would become clickable in an email that comes from the SAS address.
 */
class MailText
{
    public static function plain(?string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim((string) $text)) ?? '';
        $text = preg_replace('~(?i)\b(https?|ftp)://~', "$1\u{200B}://", $text) ?? $text;
        $text = preg_replace('~(?i)\bwww\.~', "www\u{200B}.", $text) ?? $text;

        return preg_replace('/([\\\\`*_{}\[\]()<>#+!|~&])/', '\\\\$1', $text) ?? $text;
    }
}
