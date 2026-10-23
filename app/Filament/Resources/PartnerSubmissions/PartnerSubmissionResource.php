<?php

namespace App\Filament\Resources\PartnerSubmissions;

use App\Filament\Resources\PartnerSubmissions\Pages\EditPartnerSubmission;
use App\Filament\Resources\PartnerSubmissions\Pages\ListPartnerSubmissions;
use App\Models\PartnerSubmission;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Scholarships proposed by partners. Editors check them, ask for changes, accept or reject, and turn accepted ones into drafts. */
class PartnerSubmissionResource extends Resource
{
    protected static ?string $model = PartnerSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Partners';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'partner submission';

    protected static ?string $navigationLabel = 'Partner submissions';

    public static function canCreate(): bool
    {
        return false; // partners submit from their own area
    }

    public static function form(Schema $schema): Schema
    {
        $ro = fn (string $n, ?string $label = null) => TextInput::make($n)->label($label ?? ucfirst(str_replace('_', ' ', $n)))->disabled()->dehydrated(false);

        return $schema->components([
            Section::make('What the partner sent')->columns(2)->schema([
                $ro('title'), $ro('funder_name', 'Funder'), $ro('cycle_year', 'Intake year'), $ro('intake_term', 'Intake term'),
                $ro('funding_type', 'Funding')->formatStateUsing(fn ($state) => PartnerSubmission::FUNDING[$state] ?? $state), $ro('teaching_language', 'Teaching language'),
                $ro('deadline_on', 'Closing date')->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('j F Y') : 'Not stated'), $ro('deadline_note', 'Date note'),
                TextInput::make('official_url')->label('Official page')->disabled()->dehydrated(false)->columnSpanFull(),
                Textarea::make('summary')->disabled()->dehydrated(false)->rows(3)->columnSpanFull(),
                Textarea::make('eligibility')->disabled()->dehydrated(false)->rows(3)->columnSpanFull(),
            ]),
            Section::make('Editors')->columns(1)->schema([
                Textarea::make('editor_note')->label('Message to the partner (they will see this)')->rows(3)->maxLength(2000),
                Textarea::make('internal_note')->label('Internal note (staff only)')->rows(3)->maxLength(2000),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->wrap()->limit(60),
            TextColumn::make('organisation.name')->label('Partner')->searchable(),
            TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => PartnerSubmission::STATUSES[$state] ?? $state)
                ->color(fn ($state) => match ($state) {
                    'accepted' => 'success', 'rejected' => 'danger', 'needs_changes' => 'warning', default => 'gray'
                }),
            TextColumn::make('created_at')->since()->sortable(),
        ])->filters([SelectFilter::make('status')->options(PartnerSubmission::STATUSES)])->recordActions([EditAction::make()->label('Review')])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListPartnerSubmissions::route('/'), 'edit' => EditPartnerSubmission::route('/{record}/edit')];
    }
}
