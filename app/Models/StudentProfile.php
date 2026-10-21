<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'preferred_destination_ids' => 'array',
            'subject_interest_ids' => 'array',
            'language_evidence' => 'array',
            'date_of_birth' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'nationality_country_id');
    }

    public function desiredDegreeLevel(): BelongsTo
    {
        return $this->belongsTo(DegreeLevel::class, 'desired_degree_level_id');
    }

    /** Fields shown as "incomplete" to the student; none are mandatory (STU-01). */
    public function missingFields(): array
    {
        $labels = [
            'nationality_country_id' => 'Nationality',
            'residence_country_id' => 'Country of residence',
            'desired_degree_level_id' => 'Desired degree level',
            'prior_qualification' => 'Prior qualification',
            'gpa_value' => 'Grade (in original scale)',
            'language_evidence' => 'Language evidence',
            'intake_year' => 'Intended intake year',
        ];

        return collect($labels)->filter(fn ($l, $f) => blank($this->{$f}))->all();
    }
}
