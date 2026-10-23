<?php

namespace App\Filament\Resources\ScholarshipCycles\RelationManagers;

use App\Filament\Support\DocumentRuleForm;
use App\Models\DocumentRule;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Which documents SAS needs from a student applying to this scholarship, and for which degree level.
 * Separate from "Required documents" above, which is the public checklist from the official call.
 * A rule here applies to every year of the scholarship.
 */
class DocumentRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'documentRules';

    protected static ?string $title = 'SAS document rules';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('viewAny', DocumentRule::class);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $programmeId = $this->getOwnerRecord()->programme_id;

        return $schema->components([
            DocumentRuleForm::kind(),
            DocumentRuleForm::level(),
            DocumentRuleForm::requirement()->rules(fn (Get $get, ?DocumentRule $record) => [DocumentRuleForm::unique($get('kind_id'), $programmeId, $get('degree_level_id'), $record)]),
            DocumentRuleForm::note(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['kind', 'degreeLevel']))
            ->columns([
                TextColumn::make('kind.name')->label('Document')->wrap(),
                TextColumn::make('degreeLevel.name')->label('Degree level')->placeholder('Every level'),
                DocumentRuleForm::requirementColumn(),
                TextColumn::make('note')->wrap()->limit(80),
            ])
            ->headerActions([CreateAction::make()->label('Add a rule')->authorize(fn () => auth()->user()->can('create', DocumentRule::class))])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
