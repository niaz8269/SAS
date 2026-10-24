<?php

namespace App\Filament\Resources\Testimonials;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Filament\Support\MediaPicker;
use App\Models\Testimonial;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/** Real student quotes only. Nothing is public until consent is recorded and the entry is published. */
class TestimonialResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'story or testimonial';

    protected static ?string $navigationLabel = 'Stories and testimonials';

    public static function getNavigationBadge(): ?string
    {
        $n = Testimonial::pending()->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected static function permission(): string
    {
        return 'site.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Who and where')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120)->helperText('Exactly as the person agreed to be shown: full name, first name or initials.'),
                Select::make('gender')->options(Testimonial::GENDERS)->placeholder('Not stated')->helperText('Only chooses the sketch picture when there is no photo.'),
                TextInput::make('country')->maxLength(100),
                TextInput::make('university')->maxLength(190),
                TextInput::make('scholarship')->label('Scholarship or programme')->maxLength(190)->columnSpanFull(),
                TextInput::make('context')->label('Older free-text line (staff-typed quotes only)')->maxLength(190)->columnSpanFull()
                    ->helperText('For example "Master\'s student, Tsinghua University, 2026". Shown only when country and university are empty.'),
            ]),
            Section::make('Their words')->schema([
                Textarea::make('story')->label('The full story')->rows(10)->maxLength(3000)->columnSpanFull()
                    ->helperText("The person's own words. You may fix spelling and remove private details; do not change what they say or invent anything."),
                Textarea::make('quote')->label('Short quote shown on cards')->required()->rows(3)->maxLength(1000)->columnSpanFull()
                    ->helperText('For a submitted story this starts as its first 280 characters. Keep it a true extract.'),
                MediaPicker::make('photo_path')->label('Photo (only if they agreed)')->columnSpanFull(),
            ]),
            Section::make('Permission and review')->columns(2)->schema([
                Select::make('review_status')->label('Review')->options(['pending' => 'Waiting for review', 'approved' => 'Approved', 'rejected' => 'Rejected'])->default('approved')->selectablePlaceholder(false)
                    ->helperText('Use the Approve and Reject buttons in the list, or change it here.'),
                Toggle::make('consent_given')->label('This person agreed in writing to have this published')->live(),
                DatePicker::make('consent_date')->label('Date consent was given')->maxDate(now())
                    ->required(fn (Get $get) => (bool) $get('consent_given'))->visible(fn (Get $get) => (bool) $get('consent_given')),
                Textarea::make('consent_note')->label('How consent was given (staff only)')->rows(2)->maxLength(500)->visible(fn (Get $get) => (bool) $get('consent_given'))
                    ->helperText('For example "Email of 12 Oct 2026, saved in the shared drive". Never shown publicly.'),
                Textarea::make('consent_text')->label('The wording they agreed to (saved when they sent it)')->rows(4)->disabled()->columnSpanFull()->visible(fn ($record) => filled($record?->consent_text)),
                TextInput::make('submitter_email')->label('Their email (staff only, never shown)')->email()->disabled()->visible(fn ($record) => filled($record?->submitter_email)),
                Textarea::make('review_note')->label('Staff note (never shown)')->rows(2)->maxLength(500),
                Toggle::make('published')->label('Show on the website')->disabled(fn (Get $get) => ! $get('consent_given') || $get('review_status') !== 'approved')
                    ->helperText('Can only be switched on once consent is recorded and the review is Approved.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')->label('')->disk('public_media')->circular()->size(40)->defaultImageUrl(fn (Testimonial $r) => $r->avatarUrl()),
                TextColumn::make('name')->searchable()->description(fn (Testimonial $r) => $r->placeLine()),
                TextColumn::make('quote')->label('Story')->limit(70)->wrap()->tooltip(fn (Testimonial $r) => $r->excerpt(500)),
                TextColumn::make('review_status')->label('Review')->badge()
                    ->formatStateUsing(fn ($state) => ['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected'][$state] ?? $state)
                    ->color(fn ($state) => ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('source')->badge()->color('gray')->formatStateUsing(fn ($state) => $state === 'submitted' ? 'Sent by them' : 'Typed by us')->toggleable(),
                IconColumn::make('published')->boolean(),
                TextColumn::make('submitted_at')->label('Sent')->dateTime('j M Y, H:i')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('review_status')->label('Review')->options(['pending' => 'Waiting for review', 'approved' => 'Approved', 'rejected' => 'Rejected']),
                TernaryFilter::make('published'),
            ])
            ->recordActions([
                Action::make('approve')->label('Approve and publish')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (Testimonial $r) => $r->review_status !== 'approved' && static::allowed())
                    ->requiresConfirmation()->modalDescription('It goes on the website now, with the name, country, university, story and photo (or sketch) shown here. Read it first.')
                    ->action(function (Testimonial $r) {
                        abort_unless(static::allowed(), 403);
                        $r->approve(auth()->user());
                        Notification::make()->title('Published')->success()->send();
                    }),
                Action::make('reject')->label('Reject')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (Testimonial $r) => $r->review_status === 'pending' && static::allowed())
                    ->schema([Textarea::make('note')->label('Why (staff only, never shown)')->rows(2)->maxLength(500)])
                    ->action(function (Testimonial $r, array $data) {
                        abort_unless(static::allowed(), 403);
                        $r->reject($data['note'] ?? null, auth()->user());
                        Notification::make()->title('Rejected')->send();
                    }),
                Action::make('open')->label('View')->icon(Heroicon::OutlinedEye)->visible(fn (Testimonial $r) => $r->published && $r->hasStory())
                    ->url(fn (Testimonial $r) => route('stories.show', $r->slug))->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No stories or testimonials yet')
            ->emptyStateDescription('Stories sent from the website wait here for your review. You can also add real quotes from students who agreed to be quoted. Nothing appears on the website until you approve it.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageTestimonials::route('/')];
    }
}
