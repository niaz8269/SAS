<?php

namespace App\Filament\Resources\Testimonials;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Filament\Support\MediaPicker;
use App\Models\Testimonial;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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

    protected static ?string $modelLabel = 'testimonial';

    protected static function permission(): string
    {
        return 'site.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120)->helperText('Exactly as the person agreed to be shown: full name, first name or initials.'),
            TextInput::make('context')->maxLength(190)->helperText('For example "Master\'s student, Tsinghua University, 2026". Leave blank if they prefer.'),
            Textarea::make('quote')->required()->rows(4)->maxLength(1000)->columnSpanFull()->helperText("The person's own words. Do not edit or invent quotes."),
            MediaPicker::make('photo_path')->label('Photo (only if they agreed)')->columnSpanFull(),
            Toggle::make('consent_given')->label('This person agreed in writing to have this quote published')->live()->columnSpanFull(),
            DatePicker::make('consent_date')->label('Date consent was given')->maxDate(now())
                ->required(fn (Get $get) => (bool) $get('consent_given'))->visible(fn (Get $get) => (bool) $get('consent_given')),
            Textarea::make('consent_note')->label('How consent was given (staff only)')->rows(2)->maxLength(500)->visible(fn (Get $get) => (bool) $get('consent_given'))
                ->helperText('For example "Email of 12 Oct 2026, saved in the shared drive". Never shown publicly.'),
            Toggle::make('published')->label('Show on the website')->disabled(fn (Get $get) => ! $get('consent_given'))
                ->helperText('Can only be switched on once consent is recorded.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('quote')->limit(70)->wrap(),
                IconColumn::make('consent_given')->label('Consent')->boolean(),
                IconColumn::make('published')->boolean(),
            ])
            ->filters([TernaryFilter::make('published'), TernaryFilter::make('consent_given')->label('Consent recorded')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('sort')->reorderable('sort')
            ->emptyStateHeading('No testimonials yet')
            ->emptyStateDescription('Add real quotes from students who have agreed to be quoted. Nothing appears on the website until you do.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageTestimonials::route('/')];
    }
}
