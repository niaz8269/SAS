<?php

namespace App\Filament\Resources\Audits;

use App\Filament\Resources\Audits\Pages\ManageAudits;
use App\Models\Audit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Append-only. There is no create, edit or delete (SCHEMA-05). */
class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'audit event';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('audit.view');
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
        return $table->columns([
            TextColumn::make('created_at')->dateTime('j M Y H:i:s')->sortable(),
            TextColumn::make('actor.name')->label('Actor')->placeholder('System'),
            TextColumn::make('action')->badge()->searchable(),
            TextColumn::make('auditable_type')->label('Record')->formatStateUsing(fn ($s, Audit $a) => class_basename((string) $s).' #'.$a->auditable_id),
            TextColumn::make('before')->formatStateUsing(fn ($s) => $s ? json_encode($s) : '')->limit(60)->toggleable(),
            TextColumn::make('after')->formatStateUsing(fn ($s) => $s ? json_encode($s) : '')->limit(60)->toggleable(),
            TextColumn::make('reason')->limit(60)->wrap(),
        ])->filters([
            SelectFilter::make('action')->options(fn () => Audit::query()->distinct()->orderBy('action')->pluck('action', 'action')->all())->searchable(),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageAudits::route('/')];
    }
}
