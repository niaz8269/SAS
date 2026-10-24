<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What a student is aiming for: countries, degree, subject, budget and intake. Written by the counsellor. */
class StudentGoal extends Model
{
    protected $guarded = ['id', 'student_id'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return list<string> */
    public function countryList(): array
    {
        return collect(preg_split('/[,;\n]+/', (string) $this->countries))->map(fn ($c) => trim($c))->filter()->values()->all();
    }
}
