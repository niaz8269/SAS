<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UniversityProgramme extends Model
{
    public const LEVELS = [
        'bachelor' => 'Bachelor', 'master' => "Master's", 'doctorate' => 'Doctorate', 'non_degree' => 'Non-degree', 'associate' => 'Associate / short-cycle',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['csc_eligible' => 'boolean', 'duration_years' => 'float', 'tuition_cny' => 'integer'];
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

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level] ?? $this->level;
    }

    public function languageLabel(): string
    {
        return match ($this->teaching_language) {
            '汉语', '中文', '汉语、英语' => 'Chinese',
            '英语' => 'English',
            null, '' => 'Not listed',
            default => $this->teaching_language,
        };
    }
}
