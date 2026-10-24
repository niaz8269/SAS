<?php

namespace App\Domain\Stories;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A picture sent with a story is never stored as it arrived. It is decoded, cropped to a square, shrunk and saved again as a JPEG,
 * which removes location and camera data and anything hidden in the file. It is stored under a long random name.
 */
class StoryPhoto
{
    public const SIZE = 640;

    public function store(UploadedFile $file): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Pictures cannot be processed on this server.');
        }
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > 40_000_000) {
            throw new RuntimeException('That is not a usable picture.');
        }
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source) {
            throw new RuntimeException('That picture could not be read.');
        }

        [$w, $h] = [imagesx($source), imagesy($source)];
        $side = min($w, $h);
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255)); // transparent parts become white
        imagecopyresampled($square, $source, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), self::SIZE, self::SIZE, $side, $side);
        imagedestroy($source);

        ob_start();
        imagejpeg($square, null, 86);
        $bytes = (string) ob_get_clean();
        imagedestroy($square);

        $path = 'stories/'.Str::lower(Str::random(40)).'.jpg';
        Storage::disk('public_media')->put($path, $bytes, 'public');

        return $path;
    }
}
