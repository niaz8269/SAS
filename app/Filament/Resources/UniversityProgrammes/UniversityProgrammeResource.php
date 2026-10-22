<?php

namespace App\Filament\Resources\UniversityProgrammes;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\UniversityProgrammes\Pages\ManageUniversityProgrammes;
use App\Models\UniversityProgramme;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Read-only view of the programmes a university lists on the official study-in-China system. */
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('university'))
            ->columns([
                TextColumn::make('university.name_zh')->label('University')->searchable()->sortable(),
                TextColumn::make('name_zh')->label('Programme')->searchable()->wrap(),
                TextColumn::make('level')->badge()->formatStateUsing(fn ($state) => UniversityProgramme::LEVELS[$state] ?? $state),
                IconColumn::make('csc_eligible')->label('CSC star')->boolean(),
                TextColumn::make('duration_years')->label('Years')->numeric(1),
                TextColumn::make('teaching_language')->label('Language')->placeholder('Not listed'),
                TextColumn::make('tuition_cny')->label('Tuition CNY/yr')->numeric()->sortable()->placeholder('Not listed'),
            ])
            ->filters([
                SelectFilter::make('level')->options(UniversityProgramme::LEVELS),
                SelectFilter::make('teaching_language')->label('Language')->options(fn () => UniversityProgramme::query()->whereNotNull('teaching_language')->distinct()->orderBy('teaching_language')->pluck('teaching_language', 'teaching_language')->all()),
                TernaryFilter::make('csc_eligible')->label('CSC star'),
                SelectFilter::make('university_id')->label('University')->relationship('university', 'name_zh')->searchable()->preload(false),
            ])
            ->defaultSort('university_id');
    }

    public static function getPages(): array
    {
        return ['index' => ManageUniversityProgrammes::route('/')];
    }
}
