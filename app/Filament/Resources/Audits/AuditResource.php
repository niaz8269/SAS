<?php

namespace App\Filament\Resources\Audits;

use App\Filament\Resources\Audits\Pages\ManageAudits;
use App\Models\Audit;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Append-only. There is no create, edit or delete (SCHEMA-05). */
class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'audit event';

    protected static ?string $navigationLabel = 'Staff activity';

    /** Groups of audit actions for the Topic filter. */
    public const TOPICS = [
        'student_files' => ['label' => 'Who opened student files and documents', 'like' => ['student.viewed', 'student.form_viewed', 'case.form_viewed', 'document.downloaded', 'student.documents_zipped', 'application_form.downloaded', 'document.commented']],
        'money' => ['label' => 'Fees, payments and refunds', 'like' => ['invoice.%', 'case.opened', 'case.status%']],
        'people' => ['label' => 'Roles, accounts and counsellor assignments', 'like' => ['user.%', 'student.counsellor_assigned', 'mfa.%']],
        'exports' => ['label' => 'Exports and reports', 'like' => ['report.exported', 'agent.export%']],
        'applications' => ['label' => 'Applications and reviews', 'like' => ['plan.%', 'application_form.%']],
        'system' => ['label' => 'Backups and announcements', 'like' => ['system.%', 'announcement.%']],
    ];

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
            TextColumn::make('student')->label('Student')->state(fn (Audit $a) => $a->auditable_type === (new User)->getMorphClass() ? User::find($a->auditable_id)?->name : null)->placeholder('-')->toggleable(),
            TextColumn::make('before')->formatStateUsing(fn ($s) => $s ? json_encode($s) : '')->limit(60)->toggleable(),
            TextColumn::make('after')->formatStateUsing(fn ($s) => $s ? json_encode($s) : '')->limit(60)->toggleable(),
            TextColumn::make('reason')->limit(60)->wrap(),
        ])->filters([
            SelectFilter::make('actor_id')->label('Staff member')->options(fn () => User::whereIn('id', Audit::query()->select('actor_id')->whereNotNull('actor_id'))->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            SelectFilter::make('topic')->label('Topic')->options(array_map(fn ($t) => $t['label'], self::TOPICS))
                ->query(function (Builder $query, array $data) {
                    $topic = self::TOPICS[$data['value'] ?? ''] ?? null;

                    return $topic ? $query->where(fn ($q) => collect($topic['like'])->each(fn ($like) => $q->orWhere('action', 'like', $like))) : $query;
                }),
            Filter::make('about_student')->label('About a student')->schema([TextInput::make('name')->label('Student name or email')])
                ->query(fn (Builder $query, array $data) => filled($data['name'] ?? null)
                    ? $query->where('auditable_type', (new User)->getMorphClass())->whereIn('auditable_id', User::where('name', 'like', '%'.$data['name'].'%')->orWhere('email', 'like', '%'.$data['name'].'%')->select('id'))
                    : $query),
            Filter::make('period')->label('Dates')->schema([DatePicker::make('from')->native(false), DatePicker::make('until')->native(false)])
                ->query(fn (Builder $query, array $data) => $query->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            SelectFilter::make('action')->options(fn () => Audit::query()->distinct()->orderBy('action')->pluck('action', 'action')->all())->searchable(),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageAudits::route('/')];
    }
}
