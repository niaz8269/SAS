<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class UniversityProgramme extends Model
{
    public const LEVELS = [
        'bachelor' => 'Bachelor', 'master' => "Master's", 'doctorate' => 'Doctorate', 'non_degree' => 'Non-degree', 'associate' => 'Associate / short-cycle',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['csc_eligible' => 'boolean', 'published' => 'boolean', 'duration_years' => 'float', 'tuition_cny' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $p) {
            if ($p->exists && $p->isDirty('name_en') && ! $p->isDirty('name_en_source')) {
                $p->name_en_source = 'staff';
            }
        });
    }

    /** The English name, falling back to the Chinese one so a row is never blank. */
    public function displayName(): string
    {
        return $this->name_en ?: $this->name_zh;
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('published', true)->whereHas('university', fn ($q) => $q->inCatalogue());
    }

    /** Raw stored values that read as one English label ('Chinese', 'English', ...). Cached because it scans the distinct values only. */
    public static function rawLanguagesFor(string $label): array
    {
        $all = Cache::remember('catalogue.languages', 3600, fn () => static::query()->whereNotNull('teaching_language')->distinct()->pluck('teaching_language')->all());

        return array_values(array_filter($all, fn ($l) => static::languageLabelFor($l) === $label));
    }

    /** @return list<string> the English language labels present in the catalogue */
    public static function languageLabels(): array
    {
        $all = Cache::remember('catalogue.languages', 3600, fn () => static::query()->whereNotNull('teaching_language')->distinct()->pluck('teaching_language')->all());
        $labels = array_values(array_unique(array_map(fn ($l) => static::languageLabelFor($l), $all)));
        sort($labels);

        return $labels;
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level] ?? $this->level;
    }

    /** The source writes languages in Chinese, sometimes with the English word attached ("汉语Chinese"); this is the English label. */
    public static function languageLabelFor(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return 'Not listed';
        }
        $chinese = (bool) preg_match('/汉语|中文|Chinese/u', $raw);
        $english = (bool) preg_match('/英语|英文|English/u', $raw);

        return match (true) {
            $chinese && $english => 'Chinese and English',
            $chinese => 'Chinese',
            $english => 'English',
            str_contains($raw, '双语') => 'Bilingual (Chinese and English)',
            default => $raw,
        };
    }

    public function languageLabel(): string
    {
        return self::languageLabelFor($this->teaching_language);
    }
}
