<?php

namespace Database\Factories;

use App\Enums\PublicationState;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScholarshipCycle> */
class ScholarshipCycleFactory extends Factory
{
    public function definition(): array
    {
        $year = fake()->numberBetween(2027, 2029);

        return [
            'programme_id' => ScholarshipProgramme::factory(),
            'cycle_key' => (string) $year,
            'slug' => (string) $year,
            'intake_year' => $year,
            'publication_state' => PublicationState::Draft->value,
            'summary' => fake()->sentence(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'publication_state' => PublicationState::Published->value,
            'published_at' => now(),
            'last_verified_at' => now(),
            'review_due_at' => now()->addDays(30),
        ]);
    }
}