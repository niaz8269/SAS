<?php

namespace App\Filament\Resources\BlogCategories;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\BlogCategories\Pages\ManageBlogCategories;
use App\Models\BlogCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BlogCategoryResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = BlogCategory::class;

    protected static ?string $modelLabel = 'blog category';

    protected static ?string $navigationLabel = 'Blog categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 2;

    protected static function permission(): string
    {
        return 'articles.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(80)->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ?: $set('slug', Str::slug((string) $state))),
            TextInput::make('slug')->label('Web address')->required()->alphaDash()->maxLength(96)->unique(ignoreRecord: true)->helperText('Becomes /blog/category/your-address'),
            TextInput::make('description')->label('One line shown at the top of the category')->maxLength(300),
            TextInput::make('sort')->label('Order (smaller numbers first)')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('slug')->label('Address')->prefix('/blog/category/'),
            TextColumn::make('posts_count')->counts('posts')->label('Posts'),
            TextColumn::make('sort')->label('Order'),
        ])->recordActions([EditAction::make(), DeleteAction::make()->modalDescription('Posts in this category stay on the blog without a category.')])
            ->defaultSort('sort')->emptyStateHeading('No categories yet');
    }

    public static function getPages(): array
    {
        return ['index' => ManageBlogCategories::route('/')];
    }
}
