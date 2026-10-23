<?php

namespace App\Filament\Support;

use App\Models\DegreeLevel;
use App\Models\DocumentKind;
use App\Models\DocumentRule;
use App\Models\ScholarshipProgramme;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;

/** Shared form fields and table columns for document rules, whether they are edited from a document kind or from a scholarship. */
class DocumentRuleForm
{
    public static function requirement(): Select
    {
        return Select::make('requirement')->label('Document is')->options(DocumentKind::REQUIREMENTS)->required()->default('required')
            ->helperText('Mandatory documents must be uploaded before the case counts as complete. "Not needed" hides the document for this scope.');
    }

    public static function level(): Select
    {
        return Select::make('degree_level_id')->label('Degree level')->options(fn () => DegreeLevel::orderBy('sort')->pluck('name', 'id')->all())->placeholder('Every degree level')->native(false);
    }

    public static function note(): Textarea
    {
        return Textarea::make('note')->label('Note shown to the student')->rows(2)->maxLength(300);
    }

    public static function programme(): Select
    {
        return Select::make('programme_id')->label('Scholarship')->searchable()->placeholder('Every scholarship')
            ->options(fn () => ScholarshipProgramme::orderBy('title')->pluck('title', 'id')->all());
    }

    public static function kind(): Select
    {
        return Select::make('kind_id')->label('Document')->required()->searchable()->options(fn () => DocumentKind::orderBy('sort')->pluck('name', 'id')->all());
    }

    /** Blocks a second rule for the same document, scholarship and degree level (null means "every"). */
    public static function unique(int|string|null $kindId, int|string|null $programmeId, int|string|null $levelId, ?DocumentRule $ignore): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($kindId, $programmeId, $levelId, $ignore) {
            $exists = DocumentRule::where('kind_id', $kindId)->where('programme_id', $programmeId ?: null)->where('degree_level_id', $levelId ?: null)
                ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))->exists();
            if ($exists) {
                $fail('A rule for this document, scholarship and degree level already exists. Edit that one instead.');
            }
        };
    }

    /** A link to the official page the rule comes from. */
    public static function sourceColumn(): TextColumn
    {
        return TextColumn::make('source_url')->label('Source')->placeholder('None recorded')->limit(28)
            ->url(fn ($record) => $record->source_url && str_starts_with($record->source_url, 'https://') ? $record->source_url : null, shouldOpenInNewTab: true)
            ->toggleable();
    }

    public static function requirementColumn(): TextColumn
    {
        return TextColumn::make('requirement')->label('Document is')->badge()
            ->formatStateUsing(fn ($state) => DocumentKind::REQUIREMENTS[$state] ?? $state)
            ->color(fn ($state) => match ($state) {
                'required' => 'danger', 'not_needed' => 'gray', default => 'info'
            });
    }
}
