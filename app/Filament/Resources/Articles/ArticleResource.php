<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\Articles\Pages\ManageArticles;
use App\Models\Article;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** ADM-03 guides. Editors draft; only people who can publish may set an article to Published. Every save keeps a revision. */
class ArticleResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static function permission(): string
    {
        return 'articles.manage';
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('content.publish') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $canPublish = fn (): bool => (bool) auth()->user()?->can('content.publish');

        return $schema->components([
            TextInput::make('title')->required()->maxLength(190)->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ?: $set('slug', \Illuminate\Support\Str::slug($state))),
            TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
            Select::make('category')->options(['guide' => 'Guide', 'news' => 'News', 'destination' => 'Destination', 'visa' => 'Visa', 'writing' => 'Writing'])->default('guide'),
            Select::make('status')->options(fn () => $canPublish() ? ['draft' => 'Draft', 'in_review' => 'In review', 'published' => 'Published', 'archived' => 'Archived'] : ['draft' => 'Draft', 'in_review' => 'In review'])->default('draft')->required(),
            DateTimePicker::make('published_at')->helperText('Not shown publicly before this time.'),
            Toggle::make('featured'),
            Textarea::make('excerpt')->rows(2)->maxLength(500)->columnSpanFull(),
            RichEditor::make('body')->required()->columnSpanFull()->helperText('Approved formatting only. Scripts and unknown markup are removed on display. Do not copy whole third-party articles.'),
            FileUpload::make('image_path')->label('Image')->image()->disk('public_media')->directory('articles')->visibility('public')->maxSize(4096),
            TextInput::make('image_alt')->label('Image alt text')->required(fn ($get) => filled($get('image_path'))),
            TextInput::make('image_credit')->label('Image source / licence')->required(fn ($get) => filled($get('image_path'))),
            TextInput::make('seo_title')->maxLength(70),
            Textarea::make('seo_description')->rows(2)->maxLength(320),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->limit(60),
            TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'published' => 'success', 'in_review' => 'warning', default => 'gray' }),
            TextColumn::make('published_at')->dateTime('j M Y')->sortable(),
            TextColumn::make('author.name')->label('Author'),
        ])->recordActions([
            EditAction::make()
                ->mutateDataUsing(fn (array $data) => ManageArticles::guardStatus($data))
                ->after(fn (Model $record) => ManageArticles::revision($record)),
            DeleteAction::make(),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageArticles::route('/')];
    }
}
