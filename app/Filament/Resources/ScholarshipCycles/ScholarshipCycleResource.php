<?php

namespace App\Filament\Resources\ScholarshipCycles;

use App\Enums\ApplicationStatus;
use App\Enums\PublicationState;
use App\Filament\Resources\ScholarshipCycles\Pages\CreateScholarshipCycle;
use App\Filament\Resources\ScholarshipCycles\Pages\EditScholarshipCycle;
use App\Filament\Resources\ScholarshipCycles\Pages\ListScholarshipCycles;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\BenefitsRelationManager;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\CriteriaRelationManager;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\DeadlinesRelationManager;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\DocumentRulesRelationManager;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\EvidenceRelationManager;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\MilestonesRelationManager;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\OfferingsRelationManager;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\RequirementsRelationManager;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\RoutesRelationManager;
use App\Models\ScholarshipCycle;
use BackedEnum;
use Filament\Actions\EditAction;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Annual / intake-specific calls. Permissions come from ScholarshipCyclePolicy; workflow transitions from CycleWorkflow.
 * The publication state and application status are intentionally NOT editable fields here (DATA-03).
 */
class ScholarshipCycleResource extends Resource
{
    protected static ?string $model = ScholarshipCycle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Editorial';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'scholarship cycle';

    public static function getRecordTitleAttribute(): ?string
    {
        return 'cycle_key';
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record?->label();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('programme');
    }

    public static function form(Schema $schema): Schema
    {
        $reviewer = fn (): bool => (bool) auth()->user()?->can('content.review');
        $publisher = fn (): bool => (bool) auth()->user()?->can('content.publish');

        return $schema->components([
            Section::make('Identity')->columns(3)->schema([
                Select::make('programme_id')->relationship('programme', 'title')->searchable()->preload()->required()->columnSpan(3),
                TextInput::make('cycle_key')->required()->maxLength(64)->helperText('Unique per programme, e.g. 2028 or 2028-autumn. A new year is a new cycle: never relabel an expired call.'),
                TextInput::make('slug')->required()->alphaDash()->maxLength(96),
                TextInput::make('intake_year')->numeric()->required()->minValue(2020)->maxValue(2060),
                TextInput::make('intake_term')->maxLength(32),
                TextInput::make('external_key')->disabled()->dehydrated(false)->helperText('Set by imports; used to match replays.'),
            ]),
            Section::make('Funding')->columns(2)->schema([
                Select::make('funding_type')->options(['full' => 'Full', 'partial' => 'Partial', 'tuition_only' => 'Tuition only', 'stipend_only' => 'Stipend only', 'other' => 'Other', 'unknown' => 'Not confirmed'])->required()->default('unknown'),
                Toggle::make('fully_funded_verified')->label('Covered benefits verified against the source')
                    ->helperText('Only verified calls may be labelled "Fully funded" publicly.')->disabled(fn () => ! $reviewer()),
                Textarea::make('funding_summary')->rows(2)->columnSpanFull(),
                TextInput::make('teaching_language'),
                Select::make('delivery_mode')->options(['unknown' => 'Not confirmed', 'on_campus' => 'On campus', 'online' => 'Online', 'hybrid' => 'Hybrid'])->default('unknown'),
            ]),
            Section::make('Eligibility and coverage')->columns(2)->schema([
                Toggle::make('any_nationality')->label('Open to any nationality')->helperText('Leave off and add nationalities below, or leave both empty if unconfirmed.'),
                Select::make('destinations')->relationship('destinations', 'name')->multiple()->preload()->searchable(),
                Select::make('nationalities')->relationship('nationalities', 'name')->multiple()->preload()->searchable(),
                Select::make('degreeLevels')->label('Degree levels')->relationship('degreeLevels', 'name')->multiple()->preload(),
                Select::make('subjects')->relationship('subjects', 'name')->multiple()->preload()->searchable(),
                Select::make('universities')->label('Host institutions')->relationship('universities', 'name')->multiple()->preload()->searchable(),
                Textarea::make('summary')->rows(3)->columnSpanFull(),
                Textarea::make('institutions_note')->label('Universities taking part (text)')->rows(2)->columnSpanFull()->helperText('For example: any UK university offering an eligible course. Specific universities go in Host institutions above.'),
                Textarea::make('programmes_note')->label('Programmes and fields offered')->rows(3)->columnSpanFull(),
                Textarea::make('eligibility_notes')->rows(3)->columnSpanFull(),
                Textarea::make('age_experience_notes')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Placement')->description('Sponsored placements are labelled. Payment never buys verification or changes eligibility.')->columns(3)->schema([
                Toggle::make('featured')->disabled(fn () => ! $publisher()),
                Toggle::make('sponsored')->disabled(fn () => ! $publisher()),
                TextInput::make('sponsored_label')->disabled(fn () => ! $publisher()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('programme.title')->label('Programme')->searchable()->sortable(),
            TextColumn::make('cycle_key')->label('Cycle')->sortable(),
            TextColumn::make('publication_state')->badge()->label('State')
                ->formatStateUsing(fn ($state) => $state instanceof PublicationState ? $state->label() : $state)
                ->color(fn ($state) => match ($state instanceof PublicationState ? $state->value : $state) {
                    'published' => 'success', 'in_review' => 'warning', 'changes_requested' => 'danger', 'approved' => 'info', default => 'gray',
                }),
            TextColumn::make('application_status')->label('Applications')->badge()
                ->formatStateUsing(fn ($state) => $state instanceof ApplicationStatus ? $state->label() : $state),
            TextColumn::make('next_closing_sort')->label('Next closing (UTC)')->dateTime('j M Y H:i')->sortable()->placeholder('Unconfirmed'),
            TextColumn::make('review_due_at')->label('Review due')->date()->sortable()
                ->color(fn ($record) => $record->needsRecheck() ? 'danger' : null),
            IconColumn::make('featured')->boolean(),
            TextColumn::make('last_verified_at')->date()->label('Verified')->toggleable(),
        ])->filters([
            SelectFilter::make('publication_state')->options(PublicationState::options())->label('State'),
            Filter::make('needs_recheck')->label('Needs recheck')->query(fn (Builder $query) => $query->where('publication_state', 'published')->where('review_due_at', '<', now())),
            Filter::make('closing_7')->label('Closing within 7 days')->query(fn (Builder $query) => $query->whereBetween('next_closing_sort', [now(), now()->addDays(7)])),
        ])->deferFilters(false)->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RoutesRelationManager::class, DeadlinesRelationManager::class, MilestonesRelationManager::class, OfferingsRelationManager::class, EvidenceRelationManager::class, BenefitsRelationManager::class,
            RequirementsRelationManager::class, CriteriaRelationManager::class, ReviewsRelationManager::class, DocumentRulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScholarshipCycles::route('/'),
            'create' => CreateScholarshipCycle::route('/create'),
            'edit' => EditScholarshipCycle::route('/{record}/edit'),
        ];
    }
}
