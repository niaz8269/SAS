<?php

namespace Database\Factories;

use App\Models\ScholarshipProgramme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ScholarshipProgramme> */
class ScholarshipProgrammeFactory extends Factory
{
    public function definition(): array
    {
        $title = '[DEMO] '.fake()->unique()->words(3, true).' Scholarship';

        return ['title' => $title, 'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'), 'summary' => fake()->sentence()];
    }
}
