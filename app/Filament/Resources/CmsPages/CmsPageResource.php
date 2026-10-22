<?php

namespace App\Filament\Resources\CmsPages;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\CmsPages\Pages\ManageCmsPages;
use App\Models\CmsPage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Pages written in the admin. A page with the slug about, partners, privacy, terms, refunds or accessibility replaces that built-in page once published. */
class CmsPageResource extends Resource
{
    use ChecksPermission;

    public const REPLACES = ['about' => '/about', 'partners' => '/partners', 'privacy' => '/legal/privacy', 'terms' => '/legal/terms', 'refunds' => '/legal/refunds', 'accessibility' => '/legal/accessibility'];

    protected static ?string $model = CmsPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'page';

    protected static ?string $navigationLabel = 'Pages';

    protected static function permission(): string
    {
        return 'site.manage';
    }

    public static function publicUrl(CmsPage $page): string
    {
        return url(self::REPLACES[$page->slug] ?? '/pages/'.$page->slug);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(190),
            TextInput::make('slug')->required()->alphaDash()->maxLength(120)->unique(ignoreRecord: true)
                ->helperText('The web address. "about", "partners", "privacy", "terms", "refunds" and "accessibility" replace the built-in pages; any other slug is served at /pages/your-slug.'),
            Textarea::make('meta_description')->label('Search-engine description')->rows(2)->maxLength(300)->columnSpanFull(),
            RichEditor::make('body')->columnSpanFull()->helperText('Headings, lists, links and tables are kept. Scripts and embedded code are always removed.'),
            Toggle::make('published')->helperText('Unpublished pages are not visible, and the built-in page is shown instead.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('slug')->badge()->color('gray'),
                IconColumn::make('published')->boolean(),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('view')->label('Open')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn (CmsPage $r) => static::publicUrl($r))->openUrlInNewTab()->visible(fn (CmsPage $r) => $r->published),
                EditAction::make(), DeleteAction::make(),
            ])
            ->defaultSort('title');
    }

    public static function getPages(): array
    {
        return ['index' => ManageCmsPages::route('/')];
    }
}
