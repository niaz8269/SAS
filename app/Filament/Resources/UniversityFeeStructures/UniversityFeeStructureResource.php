<?php

namespace App\Filament\Resources\UniversityFeeStructures;

use App\Filament\Concerns\ChecksPermission;
use App\Filament\Resources\UniversityFeeStructures\Pages\ManageUniversityFeeStructures;
use App\Models\UniversityFeeStructure;
use App\Models\UniversityProgramme;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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

/** What a medical programme costs a student, per university and intake. Only the university's and the government's charges, never an agent's. */
class UniversityFeeStructureResource extends Resource
{
    use ChecksPermission;

    protected static ?string $model = UniversityFeeStructure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Reference data';

    protected static ?string $modelLabel = 'fee structure';

    protected static ?string $navigationLabel = 'Medical fees';

    protected static function permission(): string
    {
        return 'taxonomy.manage';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('university_id')->label('University')->relationship('university', 'name')->searchable()->required()->columnSpanFull(),
            TextInput::make('programme_name')->required()->maxLength(190),
            Select::make('level')->options(UniversityProgramme::LEVELS)->default('bachelor')->required(),
            TextInput::make('duration_text')->label('Duration')->maxLength(190)->helperText('For example: 5 years of study plus 1 year internship (6 years)'),
            TextInput::make('duration_years')->numeric()->minValue(0.5)->maxValue(12),
            TextInput::make('teaching_language')->maxLength(60),
            TextInput::make('intake_label')->label('Intake')->maxLength(80)->helperText('For example: September 2026'),
            TextInput::make('currency')->length(3)->default('CNY')->required(),
            TextInput::make('tuition_amount')->label('Tuition (first year)')->numeric()->minValue(0),
            TextInput::make('tuition_amount_later')->label('Tuition (later years, if different)')->numeric()->minValue(0),
            Select::make('tuition_period')->options(['year' => 'Per year', 'semester' => 'Per semester'])->default('year')->required(),
            TextInput::make('tuition_note')->maxLength(300),
            TextInput::make('hostel_amount')->label('Hostel (first year, per year)')->numeric()->minValue(0),
            TextInput::make('hostel_amount_later')->label('Hostel (later years, if different)')->numeric()->minValue(0),
            TextInput::make('hostel_note')->maxLength(300),
            TextInput::make('application_fee_amount')->label('Application fee')->numeric()->minValue(0),
            Repeater::make('other_fees')->label('Other charges by the university or government')->schema([
                TextInput::make('label')->required(), TextInput::make('amount')->numeric()->required(), TextInput::make('amount_max')->label('Up to (if a range)')->numeric(),
                Select::make('when')->options(['first_year' => 'First year only', 'every_year' => 'Every year'])->default('every_year')->required(),
                Toggle::make('refundable')->label('Refundable deposit'), Toggle::make('optional')->label('Optional'), TextInput::make('note'),
            ])->columns(4)->columnSpanFull()->addActionLabel('Add a charge'),
            TextInput::make('hsk_note')->label('Chinese language (HSK)')->maxLength(400)->columnSpanFull(),
            TextInput::make('age_note')->maxLength(200),
            TextInput::make('marks_note')->maxLength(300),
            TextInput::make('application_deadline')->maxLength(200),
            TagsInput::make('required_documents')->columnSpanFull()->placeholder('Add a document and press Enter'),
            Select::make('source_type')->options(['university' => 'University page', 'government' => 'Government page', 'supplied' => 'Fee sheet given to SAS'])->default('university')->required(),
            TextInput::make('source_label')->maxLength(190),
            TextInput::make('source_url')->url()->maxLength(500),
            DatePicker::make('source_date'),
            Toggle::make('published')->label('Show on the public university page')->helperText('The university is listed only if it also has a published scholarship.')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('university'))
            ->columns([
                TextColumn::make('university.name')->label('University')->searchable()->sortable(),
                TextColumn::make('programme_name')->label('Programme')->searchable()->wrap(),
                TextColumn::make('intake_label')->label('Intake'),
                TextColumn::make('tuition_amount')->label('Tuition')->formatStateUsing(fn ($state, UniversityFeeStructure $r) => $r->money($r->tuitionPerYear()))->placeholder('-'),
                TextColumn::make('source_type')->label('Source')->badge()->formatStateUsing(fn ($s) => ['university' => 'university', 'government' => 'government', 'supplied' => 'fee sheet'][$s] ?? $s),
                IconColumn::make('published')->label('Public')->boolean(),
                IconColumn::make('has_scholarship')->label('Has scholarship')->boolean()->getStateUsing(fn (UniversityFeeStructure $r) => $r->university->scholarships()->where('published', true)->exists()),
            ])
            ->filters([SelectFilter::make('university_id')->label('University')->relationship('university', 'name')->searchable(), TernaryFilter::make('published')->label('Public')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('show')->label('Show on the public pages')->icon('heroicon-o-eye')->requiresConfirmation()->action(fn (Collection $records) => $records->each->update(['published' => true]))->deselectRecordsAfterCompletion(),
                BulkAction::make('hide')->label('Hide from the public pages')->icon('heroicon-o-eye-slash')->requiresConfirmation()->action(fn (Collection $records) => $records->each->update(['published' => false]))->deselectRecordsAfterCompletion(),
            ])])
            ->defaultSort('university_id');
    }

    public static function getPages(): array
    {
        return ['index' => ManageUniversityFeeStructures::route('/')];
    }
}
