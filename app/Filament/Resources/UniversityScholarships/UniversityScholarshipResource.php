<?php

namespace App\Filament\Resources\UniversityScholarships;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\UniversityScholarships\Pages\ManageUniversityScholarships;
use App\Models\UniversityScholarship;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Support\Collection;

/** Scholarships open to international students at one university. A university without one is not listed in the medical section. */
class UniversityScholarshipResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = UniversityScholarship::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|\UnitEnum|null $navigationGroup = 'Reference data';

    protected static ?string $modelLabel = 'university scholarship';

    protected static ?string $navigationLabel = 'University scholarships';

    protected static function permission(): string
    {
        return 'taxonomy.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('university_id')->label('University')->relationship('university', 'name')->searchable()->required()->columnSpanFull(),
            TextInput::make('name')->required()->maxLength(190)->columnSpanFull(),
            Select::make('provider')->options(UniversityScholarship::PROVIDERS)->default('university')->required(),
            TextInput::make('coverage')->label('What it covers')->maxLength(500),
            TextInput::make('amount_text')->label('Amount (as published)')->maxLength(300),
            Textarea::make('eligibility')->label('Who can get it')->rows(3)->columnSpanFull(),
            Select::make('source_type')->options(['university' => 'University page', 'government' => 'Government page', 'supplied' => 'Fee sheet given to SAS'])->default('university')->required(),
            TextInput::make('source_label')->maxLength(190),
            TextInput::make('source_url')->url()->maxLength(500)->helperText('An official page. Without one (and unless it comes from a fee sheet) the scholarship is never shown.'),
            TextInput::make('source_text')->label('Short quote from the page')->maxLength(600),
            DatePicker::make('checked_on'),
            Toggle::make('published')->label('Show on the public university page')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('university'))
            ->columns([
                TextColumn::make('university.name')->label('University')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->wrap(),
                TextColumn::make('provider')->badge()->formatStateUsing(fn ($s) => UniversityScholarship::PROVIDERS[$s] ?? $s),
                TextColumn::make('amount_text')->label('Amount')->wrap()->placeholder('-'),
                IconColumn::make('published')->label('Public')->boolean(),
            ])
            ->filters([SelectFilter::make('university_id')->label('University')->relationship('university', 'name')->searchable(), SelectFilter::make('provider')->options(UniversityScholarship::PROVIDERS), TernaryFilter::make('published')->label('Public')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('show')->label('Show on the public pages')->icon('heroicon-o-eye')->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->filter(fn ($s) => $s->source_type === 'supplied' || filled($s->source_url))->each->update(['published' => true]))->deselectRecordsAfterCompletion(),
                BulkAction::make('hide')->label('Hide from the public pages')->icon('heroicon-o-eye-slash')->requiresConfirmation()->action(fn (Collection $records) => $records->each->update(['published' => false]))->deselectRecordsAfterCompletion(),
            ])])
            ->defaultSort('university_id');
    }

    public static function getPages(): array
    {
        return ['index' => ManageUniversityScholarships::route('/')];
    }
}
