<?php

namespace App\Domain\Documents;

use App\Enums\ScanStatus;
use App\Jobs\ScanDocumentJob;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/** FILE-01..03. Server-side validation, quarantine first, release only after a clean scan. */
class DocumentService
{
    /** Extensions that must never appear anywhere in a filename (double-extension attempts). */
    private const DANGEROUS = ['php', 'phtml', 'phar', 'exe', 'dll', 'bat', 'cmd', 'com', 'sh', 'js', 'jar', 'vbs', 'ps1', 'msi',
        'scr', 'zip', 'rar', '7z', 'tar', 'gz', 'html', 'htm', 'svg', 'py', 'rb', 'pl', 'cgi', 'asp', 'aspx', 'jsp', 'lnk', 'iso'];

    public function disk(): Filesystem
    {
        return Storage::disk(config('sas.uploads.disk'));
    }

    public function validate(User $user, UploadedFile $file): string
    {
        $name = $file->getClientOriginalName();
        $parts = array_map('strtolower', explode('.', $name));
        $ext = count($parts) > 1 ? end($parts) : '';

        $fail = fn (string $m) => throw ValidationException::withMessages(['file' => $m]);

        if (! $file->isValid()) {
            $fail('The upload failed. Please try again.');
        }
        if (! in_array($ext, config('sas.uploads.extensions'), true)) {
            $fail('Only PDF, DOCX, JPEG and PNG files are accepted.');
        }
        // Double-extension attempts such as "cv.php.pdf" or "scan.exe.png".
        if (count(array_intersect(array_slice($parts, 1, -1), self::DANGEROUS)) > 0) {
            $fail('This file name is not allowed.');
        }
        if ($file->getSize() > config('sas.uploads.max_kb') * 1024) {
            $fail('The file is larger than '.(config('sas.uploads.max_kb') / 1024).' MB.');
        }
        $mime = $this->sniff($file->getRealPath(), $ext);
        if ($mime === null) {
            $fail('The file contents do not match its type.');
        }

        $used = (int) DocumentVersion::whereHas('document', fn ($q) => $q->where('user_id', $user->id))
            ->where('scan_status', '!=', ScanStatus::Rejected->value)->sum('size');
        if ($used + $file->getSize() > config('sas.uploads.quota_mb') * 1024 * 1024) {
            $fail('This upload would exceed your '.config('sas.uploads.quota_mb').' MB storage allowance.');
        }

        return $mime;
    }

    /** MIME signature check on the actual bytes, not the client-declared type. */
    public function sniff(string $path, string $ext): ?string
    {
        $head = (string) file_get_contents($path, false, null, 0, 16);

        return match ($ext) {
            'pdf' => str_starts_with($head, '%PDF-') ? 'application/pdf' : null,
            'png' => str_starts_with($head, "\x89PNG\r\n\x1a\n") ? 'image/png' : null,
            'jpg', 'jpeg' => str_starts_with($head, "\xFF\xD8\xFF") ? 'image/jpeg' : null,
            'docx' => $this->isDocx($path) ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : null,
            default => null,
        };
    }

    private function isDocx(string $path): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        $ok = $zip->locateName('word/document.xml') !== false && $zip->locateName('[Content_Types].xml') !== false;
        $zip->close();

        return $ok;
    }

    /** Stores into quarantine and queues the scan. A new version is added when $document is given. */
    public function store(User $user, UploadedFile $file, string $type, string $title, ?Document $document = null): Document
    {
        $mime = $this->validate($user, $file);
        $key = (string) Str::ulid(); // opaque storage key (FILE-02)
        $this->disk()->put($this->quarantinePath($key), fopen($file->getRealPath(), 'r'));

        $quality = $this->qualityNote($file, $mime);
        $document = DB::transaction(function () use ($user, $file, $type, $title, $document, $key, $mime, $quality) {
            $document ??= $user->documents()->create(['type' => $type, 'title' => $title]); // user_id is set by the relation, never mass-assigned
            $version = $document->versions()->create([
                'version' => ($document->versions()->max('version') ?? 0) + 1,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
                'storage_disk' => config('sas.uploads.disk'), 'storage_key' => $key, 'size' => $file->getSize(),
                'mime' => $mime, 'quality_note' => $quality, 'checksum' => hash_file('sha256', $file->getRealPath()), 'scan_status' => ScanStatus::Pending->value,
            ]);
            $document->update(['current_version_id' => $version->id]);

            return $document;
        });

        ScanDocumentJob::dispatch($document->current_version_id)->afterCommit();

        return $document;
    }

    /** A gentle warning when a photo is probably too small or too thin to read. Never blocks the upload. */
    public function qualityNote(UploadedFile $file, string $mime): ?string
    {
        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return null;
        }
        $size = @getimagesize($file->getRealPath());
        if (! $size) {
            return null;
        }
        [$w, $h] = $size;
        if (min($w, $h) < 700 || max($w, $h) < 1000) {
            return "This photo is small ({$w} x {$h} pixels). Funders often cannot read text from it. A photo of at least 1200 pixels on the long side is better.";
        }
        if ($file->getSize() < 40 * 1024) {
            return 'This file is very small ('.round($file->getSize() / 1024).' KB), so it may be blurry. Check that every word can be read.';
        }

        return null;
    }

    public function quarantinePath(string $key): string
    {
        return 'quarantine/'.$key;
    }

    public function releasedPath(string $key): string
    {
        return 'released/'.$key;
    }

    public function pathFor(DocumentVersion $v): string
    {
        return $v->scan_status === ScanStatus::Available ? $this->releasedPath($v->storage_key) : $this->quarantinePath($v->storage_key);
    }

    public function delete(Document $document): void
    {
        foreach ($document->versions as $v) {
            foreach ([$this->quarantinePath($v->storage_key), $this->releasedPath($v->storage_key)] as $p) {
                $this->disk()->delete($p);
            }
        }
        $document->delete(); // cascades versions and associations
    }
}
