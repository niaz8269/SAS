<?php

namespace App\Filament\Resources\BlogPosts;

use App\Domain\Editorial\AuditLog;
use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** The blog: write a post, add a picture, choose a category, publish now or schedule it. Only people who can publish may set a post live. */
class BlogPostResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = BlogPost::class;

    protected static ?string $modelLabel = 'blog post';

    protected static ?string $navigationLabel = 'Blog posts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 1;

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
            Section::make('The post')->columns(2)->schema([
                TextInput::make('title')->required()->maxLength(190)->live(onBlur: true)->columnSpanFull()
                    ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ?: $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')->label('Web address')->required()->alphaDash()->maxLength(190)->unique(ignoreRecord: true)
                    ->helperText('Lowercase letters, numbers and dashes. It becomes /blog/your-address and should not be changed after the post is shared.'),
                Select::make('category_id')->label('Category')->relationship('category', 'name')->searchable()->preload()
                    ->createOptionForm([
                        TextInput::make('name')->required()->maxLength(80)->live(onBlur: true)->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ?: $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')->required()->alphaDash()->unique(BlogCategory::class, 'slug'),
                    ])->createOptionUsing(fn (array $data) => BlogCategory::create($data)->getKey()),
                Textarea::make('excerpt')->label('Short summary (shown on the blog list and when shared)')->rows(2)->maxLength(500)->columnSpanFull(),
                RichEditor::make('body')->label('Post')->required()->columnSpanFull()
                    ->toolbarButtons([['bold', 'italic', 'underline', 'strike', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList', 'blockquote', 'table'], ['attachFiles'], ['undo', 'redo']])
                    ->fileAttachmentsDisk('public_media')->fileAttachmentsDirectory('blog')->fileAttachmentsVisibility('public')
                    ->helperText('Use the headings for sections. Pictures can be added with the paperclip. Scripts and unknown markup are removed when the post is shown. Please write your own words: do not copy whole articles from other sites.'),
            ]),
            Section::make('Cover picture')->columns(2)->schema([
                FileUpload::make('image_path')->label('Picture')->image()->disk('public_media')->directory('blog')->visibility('public')->maxSize(4096)->columnSpanFull()
                    ->helperText('Wide pictures work best (1200 by 630 pixels). It is shown at the top of the post and on shared links.'),
                TextInput::make('image_alt')->label('Describe the picture (for screen readers)')->required(fn ($get) => filled($get('image_path')))->maxLength(190),
                TextInput::make('image_credit')->label('Picture source or licence')->required(fn ($get) => filled($get('image_path')))->maxLength(190),
            ]),
            Section::make('Publishing')->columns(2)->schema([
                Select::make('status')->options(fn () => $canPublish() ? ['draft' => 'Draft (not on the website)', 'published' => 'Published'] : ['draft' => 'Draft (not on the website)'])
                    ->default('draft')->required()
                    ->helperText(fn () => $canPublish() ? '' : 'Only people with publishing rights can publish. Ask an administrator.'),
                DateTimePicker::make('published_at')->label('Publish date and time')->helperText('Empty means now. A time in the future schedules the post: it appears by itself at that time.'),
                Toggle::make('featured')->label('Show large at the top of the blog'),
            ]),
            Section::make('Search engines (optional)')->collapsed()->columns(1)->schema([
                TextInput::make('seo_title')->label('Title for Google (about 60 characters)')->maxLength(70),
                Textarea::make('seo_description')->label('Description for Google (about 155 characters)')->rows(2)->maxLength(320),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('image_path')->label('')->disk('public_media')->square()->size(48),
            TextColumn::make('title')->searchable()->limit(60)->description(fn (BlogPost $r) => '/blog/'.$r->slug),
            TextColumn::make('category.name')->label('Category')->badge()->color('gray'),
            TextColumn::make('state')->label('Status')->badge()
                ->getStateUsing(fn (BlogPost $r) => $r->isLive() ? 'Published' : ($r->isScheduled() ? 'Scheduled' : 'Draft'))
                ->color(fn ($state) => match ($state) {
                    'Published' => 'success', 'Scheduled' => 'warning', default => 'gray'
                }),
            TextColumn::make('published_at')->label('Date')->dateTime('j M Y, H:i')->sortable(),
            TextColumn::make('reviewed')->label('Reviewed')->badge()
                ->getStateUsing(fn (BlogPost $r) => $r->isReviewed() ? 'Reviewed '.$r->reviewed_at->format('j M Y') : 'Not reviewed')
                ->color(fn ($state) => str_starts_with($state, 'Reviewed') ? 'success' : 'warning')
                ->description(fn (BlogPost $r) => $r->isReviewed() ? ($r->reviewer?->name ?? '') : null),
            TextColumn::make('author.name')->label('Author')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('featured')->label('Featured')->badge()->getStateUsing(fn (BlogPost $r) => $r->featured ? 'Featured' : '')->color('info')->toggleable(),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published or scheduled']),
            SelectFilter::make('category_id')->label('Category')->relationship('category', 'name'),
            SelectFilter::make('review')->label('Review')->options(['no' => 'Not reviewed', 'yes' => 'Reviewed'])
                ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                    'no' => $query->whereNull('reviewed_at'), 'yes' => $query->whereNotNull('reviewed_at'), default => $query,
                }),
        ])->recordActions([
            Action::make('view')->label('View')->icon(Heroicon::OutlinedEye)->url(fn (BlogPost $r) => route('blog.show', $r->slug))->openUrlInNewTab(),
            Action::make('markReviewed')->label('Mark as reviewed')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                ->visible(fn (BlogPost $r) => ! $r->isReviewed() && auth()->user()?->can('content.review'))
                ->requiresConfirmation()->modalDescription('Confirm you have read the post and checked its facts and figures against the official sources it links to. Editing the text later clears this mark.')
                ->action(function (BlogPost $r): void {
                    abort_unless(auth()->user()?->can('content.review'), 403);
                    $r->forceFill(['reviewed_at' => now(), 'reviewed_by' => auth()->id()])->save();
                    AuditLog::record('blog.reviewed', $r);
                }),
            Action::make('markUnreviewed')->label('Needs review again')->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')
                ->visible(fn (BlogPost $r) => $r->isReviewed() && auth()->user()?->can('content.review'))
                ->action(function (BlogPost $r): void {
                    abort_unless(auth()->user()?->can('content.review'), 403);
                    $r->forceFill(['reviewed_at' => null, 'reviewed_by' => null])->save();
                    AuditLog::record('blog.review_withdrawn', $r);
                }),
            EditAction::make(),
            DeleteAction::make(),
        ])->defaultSort('id', 'desc')->emptyStateHeading('No blog posts yet')->emptyStateDescription('Choose New blog post to write the first one.');
    }

    public static function getPages(): array
    {
        return ['index' => ListBlogPosts::route('/'), 'create' => CreateBlogPost::route('/create'), 'edit' => EditBlogPost::route('/{record}/edit')];
    }

    /** Whoever lacks publishing rights cannot publish, even by tampering with the request. A published post with no date gets today's. */
    public static function guard(array $data): array
    {
        if (($data['status'] ?? null) === 'published' && ! auth()->user()?->can('content.publish')) {
            $data['status'] = 'draft';
        }
        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
