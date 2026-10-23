<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Overrides a kind's default for one scholarship, one degree level, or both.
 * The most specific matching rule wins: scholarship + level, then scholarship, then level, then the kind's default.
 */
class DocumentRule extends Model
{
    use AuditsChanges;

    protected $guarded = ['id'];

    public function kind(): BelongsTo
    {
        return $this->belongsTo(DocumentKind::class, 'kind_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgramme::class, 'programme_id');
    }

    public function degreeLevel(): BelongsTo
    {
        return $this->belongsTo(DegreeLevel::class, 'degree_level_id');
    }

    /** How specific the rule is; higher wins. */
    public function specificity(): int
    {
        return ($this->programme_id ? 2 : 0) + ($this->degree_level_id ? 1 : 0);
    }
}
