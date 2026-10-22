<?php

namespace App\Filament\Resources\EmailSuppressions;

use App\Domain\Communication\EmailSuppressions;
use App\Filament\Resources\EmailSuppressions\Pages\ManageEmailSuppressions;
use App\Models\EmailSuppression;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Addresses blocked after hard bounces or complaints. Staff can lift a block (audited) once the mailbox is confirmed working. */
class EmailSuppressionResource extends Resource
{
    protected static ?string $model = EmailSuppression::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('users.manage');
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('email')->searchable(),
            TextColumn::make('reason')->badge()->color(fn ($s) => $s === 'complaint' ? 'danger' : 'warning'),
            TextColumn::make('source'),
            TextColumn::make('created_at')->dateTime('j M Y H:i')->sortable(),
            TextColumn::make('lifted_at')->dateTime('j M Y H:i')->placeholder('Active'),
        ])->recordActions([
            Action::make('lift')->label('Lift block')->requiresConfirmation()
                ->modalDescription('Only do this once you are sure the mailbox works. Further bounces will block it again.')
                ->visible(fn (EmailSuppression $r) => $r->lifted_at === null && auth()->user()->can('users.manage'))
                ->action(fn (EmailSuppression $r) => app(EmailSuppressions::class)->lift($r, auth()->user())),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageEmailSuppressions::route('/')];
    }
}
