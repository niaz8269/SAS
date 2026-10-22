<?php

namespace App\Filament\Resources\MediaFiles\Pages;

use App\Filament\Resources\MediaFiles\MediaFileResource;
use App\Models\MediaFile;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageMediaFiles extends ManageRecords
{
    protected static string $resource = MediaFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')->label('Upload files')->icon('heroicon-o-arrow-up-tray')->modalHeading('Upload to the media library')->modalSubmitActionLabel('Upload')
                ->schema([
                    // SVG is not accepted: it can carry scripts. PDFs are served as plain downloads.
                    FileUpload::make('files')->label('Images (PNG, JPG, WebP) or PDF')->multiple()->required()->maxFiles(20)
                        ->disk(MediaFile::DISK)->directory('media')->visibility('public')->maxSize(8192)
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'application/pdf'])->storeFileNamesIn('original_names'),
                ])
                ->action(function (array $data) {
                    $names = $data['original_names'] ?? [];
                    foreach ((array) $data['files'] as $key => $path) {
                        $original = is_array($names) ? ($names[$key] ?? null) : null;
                        MediaFile::register($path, $original ? pathinfo($original, PATHINFO_FILENAME) : null);
                    }
                    Notification::make()->title(count((array) $data['files']).' file(s) added')->success()->send();
                }),
        ];
    }
}
