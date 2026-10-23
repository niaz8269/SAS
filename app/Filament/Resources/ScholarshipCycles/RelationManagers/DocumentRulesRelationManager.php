<?php

namespace App\Filament\Resources\ScholarshipCycles\RelationManagers;

use App\Domain\Documents\RulesCopier;
use App\Filament\Support\DocumentRuleForm;
use App\Models\DocumentRule;
use App\Models\ScholarshipProgramme;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
                DocumentRuleForm::sourceColumn(),
                TextColumn::make('checked_on')->label('Checked')->date('j M Y')->placeholder('Not checked')->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Add a rule')->authorize(fn () => auth()->user()->can('create', DocumentRule::class)),
                Action::make('copyRules')->label('Copy rules from another scholarship')->icon('heroicon-o-document-duplicate')
                    ->authorize(fn () => auth()->user()->can('create', DocumentRule::class))
                    ->schema([
                        Select::make('from')->label('Copy from')->required()->searchable()
                            ->options(fn () => ScholarshipProgramme::whereHas('documentRules')->where('id', '!=', $this->getOwnerRecord()->programme_id)->orderBy('title')->pluck('title', 'id')->all()),
                        Toggle::make('replace')->label('Replace the rules this scholarship already has')->helperText('Off: keep existing rules and add only the missing ones.'),
                    ])
                    ->modalDescription('Starts this scholarship from another one\'s rules. The copied rules are marked "Not checked": compare them with this scholarship\'s own official requirements and adjust.')
                    ->action(function (array $data) {
                        $added = app(RulesCopier::class)->copy(ScholarshipProgramme::findOrFail($data['from']), $this->getOwnerRecord()->programme, (bool) ($data['replace'] ?? false));
                        Notification::make()->title($added.' '.($added === 1 ? 'rule' : 'rules').' copied')->success()->send();
                    }),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
