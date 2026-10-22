<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    use AuditsChanges;

    public const DISK = 'public_media';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Removing a library entry removes the stored file. Pages that still point at it show nothing rather than a broken image.
        static::deleted(fn (self $m) => Storage::disk(self::DISK)->delete($m->path));
    }

    /** Record an already-stored upload (path on the media disk) in the library. Idempotent per path. */
    public static function register(string $path, ?string $name = null): self
    {
        $disk = Storage::disk(self::DISK);

        return static::firstOrCreate(['path' => $path], [
            'name' => $name ?: pathinfo($path, PATHINFO_FILENAME),
            'mime' => $disk->mimeType($path) ?: 'application/octet-stream',
            'size' => (int) $disk->size($path),
            'uploaded_by' => auth()->id(),
        ]);
    }

    public function url(): string
    {
        return Storage::disk(self::DISK)->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** Images for "choose from the library" pickers: path => name. */
    public static function imageOptions(): array
    {
        return static::query()->where('mime', 'like', 'image/%')->orderBy('name')->pluck('name', 'path')->all();
    }
}
