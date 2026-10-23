<?php

namespace App\Filament\Resources\DocumentKinds\RelationManagers;

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

/** Exceptions to this document's default: for a scholarship, for a degree level, or both. The most specific rule wins. */
class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    protected static ?string $title = 'Rules for a scholarship or degree level';

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
        $kindId = $this->getOwnerRecord()->getKey();

        return $schema->components([
            DocumentRuleForm::programme(),
            DocumentRuleForm::level(),
            DocumentRuleForm::requirement()->rules(fn (Get $get, ?DocumentRule $record) => [DocumentRuleForm::unique($kindId, $get('programme_id'), $get('degree_level_id'), $record)]),
            DocumentRuleForm::note(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['programme', 'degreeLevel']))
            ->columns([
                TextColumn::make('programme.title')->label('Scholarship')->placeholder('Every scholarship')->wrap(),
                TextColumn::make('degreeLevel.name')->label('Degree level')->placeholder('Every level'),
                DocumentRuleForm::requirementColumn(),
                TextColumn::make('note')->wrap()->limit(80),
            ])
            ->headerActions([CreateAction::make()->label('Add a rule')->authorize(fn () => auth()->user()->can('create', DocumentRule::class))])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
