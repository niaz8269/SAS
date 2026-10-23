<?php

namespace App\Filament\Resources\Countries;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\Countries\Pages\ManageCountries;
use App\Filament\Support\MediaPicker;
use App\Models\Country;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Countries double as nationality values and, when flagged, as study-destination pages (PUB-04). */
class CountryResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = Country::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'Reference data';

    protected static ?string $modelLabel = 'country / destination';

    protected static function permission(): string
    {
        return 'taxonomy.manage';
    }

    public static function canDelete(Model $record): bool
    {
        return false; // restricted: referenced by cycles and profiles (SCHEMA-06)
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Country')->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('code')->label('ISO code')->required()->length(2)->unique(ignoreRecord: true)->dehydrateStateUsing(fn ($s) => strtoupper($s)),
                TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('demonym')->label('Nationality adjective'),
                TagsInput::make('official_languages')->label('Official language(s)')->placeholder('Add a language')->columnSpanFull()
                    ->helperText('Students applying to a scholarship here can choose English or these languages as the language they will study in. Leave empty to use the built-in list.'),
            ]),
            Section::make('Destination guide')->description('Published guidance must carry source links and a last-reviewed date. Do not present unverified visa rules as current.')->schema([
                Toggle::make('is_destination')->label('Is a study destination'),
                Toggle::make('guidance_published')->label('Guide is published'),
                Toggle::make('featured')->label('Featured on the homepage'),
                MediaPicker::make('image_path')->label('Card photo')->columnSpanFull()->helperText('Shown on the destination cards and the guide page. Blank = a bundled illustration of the country. Use a photo you have the right to publish.'),
                Textarea::make('summary')->rows(2),
                RichEditor::make('study_guidance')->columnSpanFull(),
                Repeater::make('cost_notes')->schema([
                    TextInput::make('label')->required(), TextInput::make('amount_text')->label('Amount (as published)')->required(),
                    TextInput::make('currency')->length(3), DatePicker::make('updated_on')->required(),
                ])->columns(4)->addActionLabel('Add cost line'),
                Repeater::make('guidance_sources')->schema([TextInput::make('label')->required(), TextInput::make('url')->url()->required()])->columns(2)->addActionLabel('Add source'),
                DatePicker::make('guidance_reviewed_at')->label('Last reviewed'),
                TextInput::make('image_alt')->label('Image alt text'),
                TextInput::make('image_credit')->label('Image source / licence'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('code'),
            IconColumn::make('is_destination')->boolean()->label('Destination'),
            IconColumn::make('guidance_published')->boolean()->label('Published'),
            TextColumn::make('guidance_reviewed_at')->date()->label('Reviewed'),
        ])->filters([TernaryFilter::make('is_destination')->label('Destinations only')])
            ->recordActions([EditAction::make()])->defaultSort('name');
    }

    public static function getPages(): array
    {
        return ['index' => ManageCountries::route('/')];
    }
}
