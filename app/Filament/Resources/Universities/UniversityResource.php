<?php

namespace App\Filament\Resources\Universities;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\Universities\Pages\ManageUniversities;
use App\Filament\Support\MediaPicker;
use App\Models\University;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class UniversityResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = University::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Reference data';

    protected static ?string $modelLabel = 'university';

    protected static function permission(): string
    {
        return 'taxonomy.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Name (English)')->required()->maxLength(190),
            TextInput::make('slug')->required()->unique(ignoreRecord: true)->alphaDash(),
            Select::make('country_id')->relationship('country', 'name')->searchable()->preload(),
            TextInput::make('name_zh')->label('Name (Chinese)'),
            TextInput::make('name_en_official')->label('Wording on the official site (reference)')->disabled()->dehydrated(false),
            TextInput::make('province')->label('Province (English)'),
            TextInput::make('province_zh')->label('Province (Chinese)'),
            TextInput::make('city')->label('City (English)'),
            TextInput::make('city_zh')->label('City (Chinese)'),
            TextInput::make('website')->url(),
            TextInput::make('domain'),
            DatePicker::make('verified_at'),
            Toggle::make('published')->label('Show on the public Universities pages')->default(true)->columnSpanFull(),
            Textarea::make('summary')->label('Introduction (shown on the university page)')->rows(4)->maxLength(2000)->columnSpanFull()->helperText('Your own words. Plain text; leave blank to show nothing.'),
            MediaPicker::make('image_path')->label('Photo (optional)')->columnSpanFull(),
            Section::make('Medical profile (shown with the fees)')->columns(2)->columnSpanFull()->collapsed()->schema([
                TextInput::make('profile.established_year')->label('Founded')->numeric()->minValue(1000)->maxValue((int) date('Y')),
                TextInput::make('profile.institution_type')->label('Type of university')->maxLength(120),
                Textarea::make('profile.summary')->label('Summary')->rows(2)->maxLength(800)->columnSpanFull(),
                TextInput::make('profile.students_note')->label('Students and staff')->maxLength(400)->columnSpanFull(),
                TextInput::make('profile.accommodation_note')->label('Accommodation')->maxLength(600)->columnSpanFull(),
                TextInput::make('profile.affiliated_hospitals_note')->label('Teaching hospitals (summary)')->maxLength(400)->columnSpanFull(),
                Repeater::make('profile.affiliated_hospitals')->label('Affiliated hospitals')->schema([TextInput::make('name')->required(), TextInput::make('note')])->columns(2)->columnSpanFull()->addActionLabel('Add a hospital'),
                Repeater::make('profile.recognitions')->label('Recognition')->schema([
                    TextInput::make('body')->label('Recognised by')->required(), Select::make('status')->options(['verified' => 'Checked by SAS in the registry', 'claimed' => 'Stated by the university'])->default('claimed')->required(),
                    TextInput::make('source_label'), TextInput::make('source_url')->url(), TextInput::make('note'),
                ])->columns(2)->columnSpanFull()->addActionLabel('Add a recognition')->helperText('Mark a recognition as checked only after you have seen the university in the registry and have its link.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name (English)')->searchable()->sortable(),
                TextColumn::make('name_en_source')->label('English name')->badge()->placeholder('—')->toggleable()
                    ->formatStateUsing(fn ($s) => ['official' => 'official', 'translated' => 'to verify', 'staff' => 'checked by staff'][$s] ?? $s)
                    ->color(fn ($s) => match ($s) {
                        'official', 'staff' => 'success', 'translated' => 'warning', default => 'gray'
                    }),
                TextColumn::make('name_zh')->label('Chinese name')->searchable()->toggleable(),
                TextColumn::make('country.name'),
                TextColumn::make('province')->sortable()->toggleable(),
                TextColumn::make('city')->toggleable(),
                TextColumn::make('programmes_count')->counts('programmes')->label('Programmes')->sortable(),
                TextColumn::make('catalogue_checked_at')->label('Catalogue read')->date('j M Y')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('published')->boolean()->label('Public'),
                IconColumn::make('verified_at')->label('Verified')->boolean()->getStateUsing(fn ($record) => $record->verified_at !== null),
            ])
            ->filters([
                SelectFilter::make('province')->options(fn () => University::query()->whereNotNull('province')->distinct()->orderBy('province')->pluck('province', 'province')->all())->searchable(),
                TernaryFilter::make('published')->label('Public'),
                TernaryFilter::make('has_programmes')->label('Has programmes')
                    ->queries(true: fn ($query) => $query->has('programmes'), false: fn ($query) => $query->doesntHave('programmes'), blank: fn ($query) => $query),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('show')->label('Show on the public pages')->icon('heroicon-o-eye')->action(fn (Collection $records) => $records->each->update(['published' => true]))->deselectRecordsAfterCompletion()->requiresConfirmation(),
                BulkAction::make('hide')->label('Hide from the public pages')->icon('heroicon-o-eye-slash')->action(fn (Collection $records) => $records->each->update(['published' => false]))->deselectRecordsAfterCompletion()->requiresConfirmation(),
            ])])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return ['index' => ManageUniversities::route('/')];
    }
}
