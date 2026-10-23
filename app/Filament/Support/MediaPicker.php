<?php

namespace App\Filament\Support;

use App\Models\MediaFile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;

/** "Choose an image from the media library", with an inline option to upload a new one. Stores the library file's path. */
class MediaPicker
{
    public static function make(string $name): Select
    {
        return Select::make($name)
            ->options(fn () => self::options())
            ->allowHtml()
            ->searchable()
            ->placeholder('No image')
            ->createOptionForm([
                FileUpload::make('file')->label('Upload a new image')->required()->disk(MediaFile::DISK)->directory('media')->visibility('public')
                    ->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(4096),
            ])
            ->createOptionUsing(fn (array $data) => MediaFile::register($data['file'])->path)
            ->helperText('Pick from the media library, or use the + to upload. Cover photos are shown in 16:9 and cropped to fit, so use a wide photo. Manage all files under Website, Media library.');
    }

    /** @return array<string, string> path => HTML label with a thumbnail */
    public static function options(): array
    {
        return MediaFile::query()->where('mime', 'like', 'image/%')->orderBy('name')->get()
            ->mapWithKeys(fn (MediaFile $m) => [$m->path => '<span style="display:flex;align-items:center;gap:.5rem"><img src="'.e($m->url()).'" alt="" style="height:1.75rem;width:1.75rem;object-fit:cover;border-radius:.25rem"> '.e($m->name).'</span>'])
            ->all();
    }
}
