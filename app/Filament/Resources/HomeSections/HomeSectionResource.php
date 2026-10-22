<?php

namespace App\Filament\Resources\HomeSections;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\HomeSections\Pages\ManageHomeSections;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\SafeUrl;
use App\Models\HomeSection;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The homepage, section by section: wording, links, order, on/off. Built-in sections can be hidden; added sections can be deleted. */
class HomeSectionResource extends Resource
{
    use ChecksPermission;

    public const BUILT_IN = ['hero', 'degrees', 'featured', 'closing_soon', 'destinations', 'services', 'how', 'resources', 'consultation'];

    protected static ?string $model = HomeSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'homepage section';

    protected static ?string $navigationLabel = 'Homepage';

    protected static function permission(): string
    {
        return 'site.manage';
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed() && ! in_array($record->key, self::BUILT_IN, true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->label('Name in this list')->required()->maxLength(80)->helperText('Only staff see this.'),
            Toggle::make('enabled')->label('Show on the homepage')->default(true),
            TextInput::make('eyebrow')->label('Small label above the heading')->maxLength(255),
            Textarea::make('heading')->rows(2)->maxLength(255)->helperText('Press Enter for a line break (the hero heading uses it).'),
            TextInput::make('subheading')->maxLength(500)->helperText('Hero: the title of the search box. Other sections: a line under the heading.'),
            Textarea::make('body')->rows(3)->columnSpanFull(),
            TextInput::make('cta_label')->label('Button / link text')->maxLength(80),
            TextInput::make('cta_url')->label('Button / link address')->rules(SafeUrl::rules())->maxLength(500)->helperText('A path like /scholarships, or https://…'),
            TextInput::make('cta2_label')->label('Second button text')->maxLength(80),
            TextInput::make('cta2_url')->label('Second button address')->rules(SafeUrl::rules())->maxLength(500),
            Repeater::make('items')->label('Cards / bullet points')->schema([
                TextInput::make('title')->required()->maxLength(120),
                Textarea::make('text')->rows(2)->maxLength(400),
            ])->reorderable()->collapsible()->defaultItems(0)->columnSpanFull()
                ->helperText('Used by the hero (three bullets), "How the platform works" and added sections.'),
            MediaPicker::make('image_path')->label('Image (optional)')->columnSpanFull()
                ->helperText('Hero: replaces the built-in illustration. Added sections: shown beside the text. Pick from the media library or use the + to upload.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('heading')->limit(50)->wrap(),
                TextColumn::make('section_type')->label('Type')->badge()->color('gray')
                    ->getStateUsing(fn (HomeSection $record) => in_array($record->key, self::BUILT_IN, true) ? 'built-in' : 'added'),
                ToggleColumn::make('enabled')->label('Visible')->disabled(fn () => ! static::allowed()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()->visible(fn (HomeSection $r) => static::canDelete($r))])
            ->defaultSort('sort')->reorderable('sort')
            ->emptyStateHeading('No homepage sections');
    }

    public static function getPages(): array
    {
        return ['index' => ManageHomeSections::route('/')];
    }
}
