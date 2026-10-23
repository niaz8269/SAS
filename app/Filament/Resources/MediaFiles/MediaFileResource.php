<?php

namespace App\Filament\Resources\MediaFiles;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\MediaFiles\Pages\ManageMediaFiles;
use App\Models\MediaFile;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Images and PDFs for the public site. Upload here, then pick them in any image field or insert them into a page. */
class MediaFileResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = MediaFile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'file';

    protected static ?string $navigationLabel = 'Media library';

    protected static function permission(): string
    {
        return 'site.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(190),
            TextInput::make('credit')->label('Photo credit (shown on the Image credits page)')->maxLength(255)->helperText('Photographer and source, for example Jane Doe, Unsplash. Required by many licences.'),
            TextInput::make('alt')->label('Description (for screen readers)')->maxLength(255)
                ->helperText('Describe what the image shows. Leave blank for purely decorative images.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview')->label('')->getStateUsing(fn (MediaFile $r) => $r->isImage() ? $r->url() : null)->square()->imageSize(56)
                    ->defaultImageUrl('data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56"><rect width="56" height="56" rx="8" fill="#888" fill-opacity=".3"/><text x="28" y="33" font-size="14" text-anchor="middle" fill="#888">PDF</text></svg>')),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('mime')->label('Type')->badge()->color('gray'),
                TextColumn::make('size')->formatStateUsing(fn ($s) => $s >= 1048576 ? round($s / 1048576, 1).' MB' : max(1, round($s / 1024)).' KB')->sortable(),
                TextColumn::make('address')->label('Address')->getStateUsing(fn (MediaFile $r) => $r->url())->copyable()->copyMessage('Address copied')->limit(36)->toggleable(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([SelectFilter::make('kind')->label('Type')->options(['image' => 'Images', 'pdf' => 'PDFs'])
                ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                    'image' => $query->where('mime', 'like', 'image/%'), 'pdf' => $query->where('mime', 'application/pdf'), default => $query
                })])
            ->recordActions([EditAction::make(), DeleteAction::make()->modalDescription('The file is removed from the site. Pages that still use it will show no image.')])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageMediaFiles::route('/')];
    }
}
