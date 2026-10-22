<?php

namespace App\Filament\Resources\PageBlocks;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\PageBlocks\Pages\ManagePageBlocks;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\SafeUrl;
use App\Models\PageBlock;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Sections of the scholarship directory, destinations, services, resources and contact pages: the built-in
 * heading, introduction and messages, plus blocks staff add above or below the main content.
 */
class PageBlockResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = PageBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'page section';

    protected static ?string $navigationLabel = 'Other pages';

    protected static function permission(): string
    {
        return 'site.manage';
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed() && ! $record->isBuiltIn();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('page')->options(PageBlock::PAGES)->required()->disabled(fn (?PageBlock $record) => $record?->isBuiltIn() ?? false)->dehydrated(),
            TextInput::make('label')->label('Name in this list')->required()->maxLength(80)->helperText('Only staff see this.'),
            Select::make('position')->options(['top' => 'Above the page content', 'bottom' => 'Below the page content'])->default('top')
                ->visible(fn (?PageBlock $record) => ! ($record?->isBuiltIn() ?? false))->helperText('Where an added section appears. Built-in sections keep their place.'),
            Toggle::make('enabled')->label('Show on the page')->default(true)
                ->disabled(fn (?PageBlock $record) => $record?->key === 'intro')->helperText(fn (?PageBlock $record) => $record?->key === 'intro' ? 'The heading and introduction always show.' : null),
            TextInput::make('eyebrow')->label('Small label above the heading')->maxLength(255),
            Textarea::make('heading')->rows(2)->maxLength(255)->helperText('For built-in "heading and introduction" sections this is the page title (h1).'),
            Textarea::make('subheading')->rows(2)->maxLength(500)->label('Subheading / search-engine description')
                ->helperText('Introductions: this is the description search engines show. Added sections: a line under the heading.'),
            Textarea::make('body')->rows(4)->columnSpanFull(),
            TextInput::make('cta_label')->label('Button / link text')->maxLength(80),
            TextInput::make('cta_url')->label('Button / link address')->rules(SafeUrl::rules())->maxLength(500),
            TextInput::make('cta2_label')->label('Second button text')->maxLength(80)->visible(fn (?PageBlock $record) => ! ($record?->isBuiltIn() ?? false)),
            TextInput::make('cta2_url')->label('Second button address')->rules(SafeUrl::rules())->maxLength(500)->visible(fn (?PageBlock $record) => ! ($record?->isBuiltIn() ?? false)),
            Repeater::make('items')->label('Cards')->schema([
                TextInput::make('title')->required()->maxLength(120),
                Textarea::make('text')->rows(2)->maxLength(400),
            ])->reorderable()->collapsible()->defaultItems(0)->columnSpanFull()->visible(fn (?PageBlock $record) => ! ($record?->isBuiltIn() ?? false)),
            MediaPicker::make('image_path')->label('Image (optional)')->columnSpanFull()->visible(fn (?PageBlock $record) => ! ($record?->isBuiltIn() ?? false)),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('page')->badge()->formatStateUsing(fn ($s) => PageBlock::PAGES[$s] ?? $s)->sortable(),
                TextColumn::make('label')->searchable()->wrap(),
                TextColumn::make('section_type')->label('Type')->badge()->color('gray')->getStateUsing(fn (PageBlock $r) => $r->isBuiltIn() ? 'built-in' : 'added ('.$r->position.')'),
                ToggleColumn::make('enabled')->label('Visible')->disabled(fn (?PageBlock $record) => ! static::allowed() || $record?->key === 'intro'),
            ])
            ->filters([SelectFilter::make('page')->options(PageBlock::PAGES)])
            ->recordActions([EditAction::make(), DeleteAction::make()->visible(fn (PageBlock $r) => static::canDelete($r))])
            ->defaultSort('sort')->reorderable('sort')->defaultGroup('page');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePageBlocks::route('/')];
    }
}
