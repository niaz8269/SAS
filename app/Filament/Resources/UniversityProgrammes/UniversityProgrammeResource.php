<?php

namespace App\Filament\Resources\UniversityProgrammes;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\UniversityProgrammes\Pages\ManageUniversityProgrammes;
use App\Models\UniversityProgramme;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/** The programmes a university lists on the official study-in-China system. Staff can correct names and details; imports never overwrite staff-entered English names. */
class UniversityProgrammeResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = UniversityProgramme::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Reference data';

    protected static ?string $modelLabel = 'university programme';

    protected static function permission(): string
    {
        return 'taxonomy.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('university_id')->label('University')->relationship('university', 'name')->searchable()->preload(false)->required()->columnSpanFull(),
            Select::make('level')->options(UniversityProgramme::LEVELS)->required(),
            TextInput::make('name_en')->label('Programme name (English)')->maxLength(255)->helperText('Saving a changed English name marks it as checked by staff.'),
            TextInput::make('name_en_official')->label('Wording on the official site (reference)')->disabled()->dehydrated(false)->helperText('Copied from campuschina.org; it has typos, so it is shown for comparison only.'),
            TextInput::make('name_zh')->label('Programme name (Chinese)')->required()->maxLength(255),
            TextInput::make('teaching_language')->label('Teaching language')->maxLength(48)->helperText('English or Chinese wording both work; the list shows it in English.'),
            TextInput::make('duration_years')->label('Duration (years)')->numeric()->minValue(0)->maxValue(12),
            TextInput::make('tuition_cny')->label('Tuition (CNY per year)')->numeric()->minValue(0),
            Toggle::make('published')->label('Show on the public programme pages')->default(true),
            Toggle::make('csc_eligible')->label('Marked open to Chinese Government Scholarship applicants'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('university'))
            ->columns([
                TextColumn::make('university.name')->label('University')->description(fn (UniversityProgramme $r) => $r->university?->name_zh)->searchable(['universities.name', 'universities.name_zh'])->sortable(),
                TextColumn::make('name_en')->label('Programme')->getStateUsing(fn (UniversityProgramme $r) => $r->displayName())->description(fn (UniversityProgramme $r) => $r->name_en ? $r->name_zh : null)
                    ->searchable(['name_en', 'name_zh'])->wrap(),
                TextColumn::make('name_en_source')->label('English name')->badge()->placeholder('missing')->toggleable()
                    ->formatStateUsing(fn ($s) => ['official' => 'official', 'translated' => 'to verify', 'staff' => 'checked by staff'][$s] ?? $s)
                    ->color(fn ($s) => match ($s) {
                        'official', 'staff' => 'success', 'translated' => 'warning', default => 'gray'
                    }),
                TextColumn::make('name_en_official')->label('Official site wording')->placeholder('—')->wrap()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('level')->badge()->formatStateUsing(fn ($state) => UniversityProgramme::LEVELS[$state] ?? $state),
                IconColumn::make('csc_eligible')->label('CSC star')->boolean(),
                IconColumn::make('published')->label('Public')->boolean()->toggleable(),
                TextColumn::make('duration_years')->label('Years')->numeric(1),
                TextColumn::make('teaching_language')->label('Language')->getStateUsing(fn (UniversityProgramme $r) => $r->languageLabel()),
                TextColumn::make('tuition_cny')->label('Tuition CNY/yr')->numeric()->sortable()->placeholder('Not listed'),
            ])
            ->filters([
                SelectFilter::make('level')->options(UniversityProgramme::LEVELS),
                SelectFilter::make('teaching_language')->label('Language')
                    ->options(fn () => UniversityProgramme::query()->whereNotNull('teaching_language')->distinct()->pluck('teaching_language')->map(fn ($l) => UniversityProgramme::languageLabelFor($l))->unique()->sort()->mapWithKeys(fn ($l) => [$l => $l])->all())
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereIn('teaching_language', UniversityProgramme::query()->whereNotNull('teaching_language')->distinct()->pluck('teaching_language')->filter(fn ($l) => UniversityProgramme::languageLabelFor($l) === $data['value'])->all())
                        : $query),
                TernaryFilter::make('csc_eligible')->label('CSC star'),
                SelectFilter::make('university_id')->label('University')->relationship('university', 'name')->searchable()->preload(false),
                SelectFilter::make('name_en_source')->label('English name')->options(['official' => 'Official', 'translated' => 'Translated, to verify', 'staff' => 'Checked by staff']),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('university_id');
    }

    public static function getPages(): array
    {
        return ['index' => ManageUniversityProgrammes::route('/')];
    }
}
